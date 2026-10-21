<?php
/** Included by ownership-c2.php: focused C2 pre-commit regression/readiness tests. */
// OTP absence affects OTP-dependent approval, not receipt-based invoice decisions.
$sms_adapter=false;
$pair=setup_pair();[$s,$t,$p,$ids]=$pair;
$case=draft_case($pair,'CLAIM_CURRENT_PHONE',true);reviewing($case);$uid=2;
denied(static function()use($case){Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','OTP_VERIFICATION','no challenge');},'phone OTP approval requires a challenge');
$otp=Biawin_CRM_Ownership_OTP::issue($case,version($case),$p);
check(Biawin_CRM_Ownership_Repository::decode(row('ownership_otp_challenges',$otp)->provider_meta_json)['accepted']===false,'missing adapter records failed delivery');
check(!Biawin_CRM_Ownership_OTP::verify($case,version($case),$otp,$p,'CONFIRM_PHONE_CONTROL','123456'),'undelivered challenge cannot verify');
// Corrupt verification timestamps alone must not turn a delivery failure into evidence.
Biawin_CRM_Ownership_Repository::update('ownership_otp_challenges',['verified_at'=>gmdate('Y-m-d H:i:s'),'attempt_count'=>1],['id'=>$otp]);
denied(static function()use($case){Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','OTP_VERIFICATION','undelivered');},'timestamps without accepted delivery cannot approve');
Biawin_CRM_Ownership_Repository::update('ownership_otp_challenges',['sent_at'=>gmdate('Y-m-d H:i:s',time()-120)],['id'=>$otp]);
$sms_adapter=true;
$otp=Biawin_CRM_Ownership_OTP::issue($case,version($case),$p);$code=$sms[count($sms)-1][1];
denied(static function()use($case){Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','OTP_VERIFICATION','unverified');},'delivery alone cannot approve');
check(Biawin_CRM_Ownership_OTP::verify($case,version($case),$otp,$p,'CONFIRM_PHONE_CONTROL',$code),'delivered challenge verifies');
Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','OTP_VERIFICATION','delivered and verified');
check(row('ownership_cases',$case)->status==='AWAITING_SUPERVISOR_APPROVAL','OTP does not bypass independent high-risk approval');
$sms_adapter=false;
$case=draft_case(setup_pair());reviewing($case);approve($case);
check(row('ownership_cases',$case)->status==='DECIDED','missing SMS adapter does not affect invoice-only receipt approval');
$sms_adapter=true;

// Explicit historical readiness: bounded pages, exact idempotence, ambiguity and retry.
$pair=setup_pair(2);[$s,$t,$p,$ids]=$pair;$start=$ids[0]-1;$uid=1;
denied(static function()use($start){Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($start,1);},'ordinary expert cannot run readiness mutation');
$uid=3;$first=Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($start,1);
check($first['records'][0]['status']==='LOCK_CREATED' && $first['next_after_id']===$ids[0] && $first['has_more'],'clean existing invoice gets lock with bounded cursor');
$locks=$wpdb->get_results('SELECT * FROM test_bcrm_ownership_locks');$events=$wpdb->get_results('SELECT * FROM test_bcrm_ownership_events');
$repeat=Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($start,1);
check($repeat['records'][0]['status']==='ALREADY_LOCKED' && $wpdb->get_results('SELECT * FROM test_bcrm_ownership_locks')==$locks && $wpdb->get_results('SELECT * FROM test_bcrm_ownership_events')==$events,'repeat preserves existing locks and events byte for byte');
$wpdb->query("CREATE TRIGGER readiness_fail BEFORE INSERT ON test_bcrm_ownership_events WHEN NEW.event_type='OWNERSHIP_LOCK_CREATED' BEGIN SELECT RAISE(ABORT,'injected readiness failure'); END");
$failed=Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($first['next_after_id'],1);
check($failed['failed_invoice_id']===$ids[1] && $failed['next_after_id']===$ids[0] && $failed['has_more'],'failure keeps retry cursor before failed invoice');
check(!(int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_ownership_locks WHERE entity_type='INVOICE' AND entity_id=".$ids[1]),'failed event rolls back new lock');
$wpdb->query('DROP TRIGGER readiness_fail');
$resumed=Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($failed['next_after_id'],1);
check($resumed['records'][0]['status']==='LOCK_CREATED' && $resumed['error']===null,'retry creates missing lock exactly once');
$pair=setup_pair();[$s,$t,$p,$ids]=$pair;Biawin_CRM_Ownership_Repository::update('customers',['profile_completeness'=>'incomplete'],['id'=>$s]);
$r=Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($ids[0]-1,1);
check($r['records'][0]['status']==='INCOMPLETE_SKIPPED','initial sync skips incomplete profile');
$pair=setup_pair();[$s,$t,$p,$ids]=$pair;
add('invoice_phones',['invoice_id'=>$ids[0],'normalized_phone'=>'09912345678','created_at'=>Biawin_CRM_DB::now()]);
$r=Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($ids[0]-1,1);
check($r['records'][0]['status']==='HISTORICAL_REVIEW_REQUIRED' && in_array('multiple_phone_candidates',$r['records'][0]['issues'],true),'ambiguous phones reported without a guess');
check(!(int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_ownership_locks WHERE entity_type='INVOICE' AND entity_id=".$ids[0]),'ambiguous invoice receives no unsafe lock');
$pair=setup_pair();[$s,$t,$p,$ids]=$pair;
Biawin_CRM_Ownership_Repository::update('services',['customer_id'=>$t],['invoice_id'=>$ids[0]]);
$r=Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($ids[0]-1,1);
check(in_array('service_owner_mismatch',$r['records'][0]['issues'],true),'historical service owner conflict reported');
$pair=setup_pair();[$s,$t,$p,$ids]=$pair;
$phone=row('phones',$p)->normalized_phone;
add('invoice_phones',['invoice_id'=>$ids[0],'normalized_phone'=>substr($phone,1),'created_at'=>Biawin_CRM_DB::now()]);
$r=Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($ids[0]-1,1);
check($r['records'][0]['status']==='LOCK_CREATED','deterministic normalized historical representations receive lock without rewriting phone data');

// No gate for unrelated work, even if ownership gate is unavailable elsewhere.
$wpdb->lock_available=false;$gates=$wpdb->gate_calls;
check(Biawin_CRM_DB::write('query','START TRANSACTION')!==false,'unrelated transaction starts without ownership gate');
check(Biawin_CRM_DB::write('insert','test_bcrm_catalog_items',['catalog'=>'test','item_key'=>'unrelated','label'=>'Allowed','created_at'=>Biawin_CRM_DB::now(),'updated_at'=>Biawin_CRM_DB::now()])===1,'unrelated catalog write succeeds with unavailable ownership gate');
check(Biawin_CRM_DB::write('query','COMMIT')!==false && $wpdb->gate_calls===$gates,'unrelated transaction acquires no ownership gate');
$wpdb->lock_available=true;
$gates=$wpdb->gate_calls;Biawin_CRM_DB::write('query','START TRANSACTION');
check($wpdb->gate_calls===$gates,'legacy begin acquires no gate');
Biawin_CRM_DB::write('update','test_bcrm_customers',['display_name'=>'ordinary'],['id'=>$s]);
check($wpdb->gate_calls===$gates+1,'first protected mutation acquires gate');
Biawin_CRM_DB::write('update','test_bcrm_customers',['display_name'=>'ordinary again'],['id'=>$s]);
check($wpdb->gate_calls===$gates+1,'protected transaction retains one gate across writes');
check((bool)array_filter($wpdb->queries,static function($q)use($s){return strpos($q,'customer_holds')!==false && substr($q,-10)==='FOR UPDATE';}),'hold guard uses current reads after delayed transaction gate');
Biawin_CRM_DB::write('query','ROLLBACK');
$wpdb->lock_available=false;$gates=$wpdb->gate_calls;
check(Biawin_CRM_DB::write('insert','test_bcrm_customers',['internal_uuid'=>'new-unrelated-profile','created_at'=>Biawin_CRM_DB::now(),'updated_at'=>Biawin_CRM_DB::now()])===1 && $wpdb->gate_calls===$gates,'plain new profile insert needs no ownership gate');
$wpdb->lock_available=true;

// Execute C1 and C2 against identical SQL fixtures; compare results, DML and all side effects.
class OwnershipTraceFixture extends SQLFixture {
    public $arguments=[];
    function insert($table,$data,$format=null){$this->arguments[]=['insert',func_get_args()];return parent::insert(...func_get_args());}
    function update($table,$data,$where,$format=null,$where_format=null){$this->arguments[]=['update',func_get_args()];return parent::update(...func_get_args());}
    function delete($table,$where,$format=null){$this->arguments[]=['delete',func_get_args()];return parent::delete(...func_get_args());}
    function replace($table,$data,$format=null){
        $this->arguments[]=['replace',func_get_args()];
        $cols=implode(',',array_keys($data));$vals=implode(',',array_map(function($v){return $v===null?'NULL':$this->pdo->quote((string)$v);},array_values($data)));
        return $this->query('REPLACE INTO '.$table.' ('.$cols.') VALUES ('.$vals.')');
    }
}
function hardening_state(): array {
    global $wpdb;$out=[];
    foreach($wpdb->pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table) $out[$table]=$wpdb->pdo->query('SELECT * FROM '.$table.' ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
    return $out;
}
function hardening_run(callable $work): array {
    global $wpdb;
    $wpdb->queries=[];$wpdb->arguments=[];$wpdb->last_error='';$wpdb->insert_id=0;$wpdb->rows_affected=0;
    try{$value=$work();$error=null;}catch(Throwable $e){$value=null;$error=[get_class($e),$e->getMessage()];}
    return ['result'=>$value,'exception'=>$error,'arguments'=>$wpdb->arguments,'sql'=>array_values(array_filter($wpdb->queries,static function($q){return preg_match('/^(INSERT|UPDATE|DELETE|REPLACE|START TRANSACTION|COMMIT|ROLLBACK)\b/',$q);})),
        'metadata'=>[$wpdb->insert_id,$wpdb->rows_affected,$wpdb->last_error],'state'=>hardening_state()];
}
function hardening_compare(callable $baseline,callable $current,string $label): void {
    global $wpdb;
    Biawin_CRM_DB::write('query','START TRANSACTION');
    $wpdb->pdo->exec('SAVEPOINT differential');$before=hardening_run($baseline);$wpdb->pdo->exec('ROLLBACK TO differential');
    if(strpos($label,'actual C1/C2')===0) check($before['exception']===null && ($before['result']===true || ($before['result']['ok'] ?? false)===true) && count($before['sql'])>0,$label.' exercises a successful real business mutation');
    $after=hardening_run($current);$wpdb->pdo->exec('ROLLBACK TO differential');$wpdb->pdo->exec('RELEASE differential');
    Biawin_CRM_DB::write('query','ROLLBACK');
    check($before===$after,$label.' preserves SQL/arguments/return/exception/metadata/database effects');
}
$saved_wpdb=$wpdb;$wpdb=new OwnershipTraceFixture();fixture_schema();
$finance_source=file_get_contents(__DIR__.'/../includes/class-biawin-crm-finance-review.php');
preg_match('/CREATE TABLE IF NOT EXISTS \$table \((.*?)\n        \) \$charset/s',$finance_source,$finance_schema);
$receipt_sql=preg_replace('/,\s*KEY \w+ \([^\n]*\)/','','CREATE TABLE test_bcrm_receipt_reviews ('.$finance_schema[1]."\n)");
check($wpdb->query($receipt_sql)!==false,'finance receipt fixture uses real independent schema: '.$wpdb->last_error);
$fixed_time='2026-10-21 12:00:00';$fixed_rand=42;
$now=Biawin_CRM_DB::now();$uid=2;
$s=add('customers',['internal_uuid'=>'source','display_name'=>'Source','declared_paid'=>2000000,'profile_completeness'=>'incomplete','created_at'=>$now,'updated_at'=>$now]);
$t=add('customers',['internal_uuid'=>'target','display_name'=>'Target','declared_paid'=>2000000,'profile_completeness'=>'incomplete','created_at'=>$now,'updated_at'=>$now]);
$p=add('phones',['customer_id'=>$s,'raw_phone'=>'09123456789','normalized_phone'=>'09123456789','created_at'=>$now]);
$invoice=add('invoices',['customer_id'=>$s,'invoice_code'=>'LEGACY','amount_paid'=>1000000,'created_at'=>$now,'updated_at'=>$now]);
add('invoice_phones',['invoice_id'=>$invoice,'normalized_phone'=>'09123456789','created_at'=>$now]);
add('services',['customer_id'=>$s,'invoice_id'=>$invoice,'service_name'=>'existing','created_at'=>$now]);
add('interactions',['customer_id'=>$s,'agent_user_id'=>1,'type'=>'call','declared_paid'=>2000000,'created_at'=>$now]);
add('interactions',['customer_id'=>$t,'agent_user_id'=>1,'type'=>'call','declared_paid'=>2000000,'created_at'=>$now]);

// Load actual committed C1 sources in a separate class namespace, retaining their dependencies.
$class_map=['Biawin_CRM_DB'=>'C1_Biawin_CRM_DB','Biawin_CRM_Customer_Service'=>'C1_Biawin_CRM_Customer_Service','Biawin_CRM_Workflow_Service'=>'C1_Biawin_CRM_Workflow_Service','Biawin_CRM_Deficiency_Workflow'=>'C1_Biawin_CRM_Deficiency_Workflow'];
foreach(['db','customer-service','workflow-service','deficiency-workflow'] as $name){
    $source=shell_exec('git show 991c3bac815c9fe153096831107441f2194ed632:includes/class-biawin-crm-'.$name.'.php');
    if(!$source)throw new RuntimeException('Cannot load committed C1 fixture');
    eval(strtr(substr($source,5),$class_map));
}
hardening_compare(static function()use($s){return C1_Biawin_CRM_Customer_Service::recalculate_customer($s);},static function()use($s){return Biawin_CRM_Customer_Service::recalculate_customer($s);},'actual C1/C2 recalculation and Deficiency routing');
hardening_compare(static function()use($t){return C1_Biawin_CRM_Customer_Service::attach_invoice($t,'LEGACY','test',false);},static function()use($t){return Biawin_CRM_Customer_Service::attach_invoice($t,'LEGACY','test',false);},'actual C1/C2 unlocked invoice attachment and side effects');
hardening_compare(static function()use($s,$p){return C1_Biawin_CRM_Customer_Service::set_phone_verification($s,$p,'verified',true);},static function()use($s,$p){return Biawin_CRM_Customer_Service::set_phone_verification($s,$p,'verified',true);},'actual C1/C2 phone verification');
hardening_compare(static function()use($s){return C1_Biawin_CRM_Workflow_Service::set_customer_journey($s,'no_request','','note');},static function()use($s){return Biawin_CRM_Workflow_Service::set_customer_journey($s,'no_request','','note');},'actual C1/C2 journey transition');

// Gateway composition covers every held-profile table used across the audited controllers.
$complaint=add('complaints',['customer_id'=>$s,'created_at'=>$now]);
$deficiency=add('deficiency_cases',['customer_id'=>$s,'case_code'=>'FIXTURE','created_at'=>$now,'updated_at'=>$now]);
$tables=['customers','invoices','phones','invoice_phones','invoice_items','payments','services','interactions','assignments','complaints','complaint_inquiries','financial_cases','service_cases','refunds','refund_registers','attachments','activities','deficiency_cases','case_actions','customer_tags','merge_events','receipt_reviews'];
foreach($tables as $name){
    $table='test_bcrm_'.$name;$columns=$wpdb->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);$data=[];$column_names=array_column($columns,'name');
    foreach($columns as $col){if($col['name']!=='id' && $col['notnull'] && $col['dflt_value']===null)$data[$col['name']]=preg_match('/INT|DECIMAL|REAL/i',$col['type'])?0:'fixture';}
    if(in_array('customer_id',$column_names,true))$data['customer_id']=$s;
    if(in_array('invoice_id',$column_names,true))$data['invoice_id']=$invoice;
    if(in_array('complaint_id',$column_names,true))$data['complaint_id']=$complaint;
    if($name==='receipt_reviews')$data['case_id']=$deficiency;
    if($name==='customers')$data['internal_uuid']='matrix-profile';
    if($name==='invoices')$data['invoice_code']='MATRIX';
    if(in_array('normalized_phone',$column_names,true))$data['normalized_phone']='09911112222';
    if($name==='attachments')$data['object_type']='customer';
    if($name==='merge_events'){$data['primary_customer_id']=$s;$data['merged_customer_ids']=wp_json_encode([$t]);}
    $id=add($name,$data);
    $key=in_array('id',$column_names,true)?'id':'attachment_id';
    if($key==='attachment_id')$id=$data[$key];
    $edit=[$key=>$id];
    foreach(['updated_at','created_at','review_note','note'] as $field)if(in_array($field,$column_names,true)){$edit=[$field=>strpos($field,'_at')!==false?'2026-10-20 12:00:00':'changed'];break;}
    foreach(['update','delete','replace'] as $method){
        if($method==='update')$args=[$table,$edit,[$key=>$id],['%s'],['%d']];
        elseif($method==='delete')$args=[$table,[$key=>$id],['%d']];
        else $args=[$table,[$key=>$id]+$data];
        hardening_compare(static function()use($method,$args){global $wpdb;return $wpdb->$method(...$args);},static function()use($method,$args){return Biawin_CRM_DB::write($method,...$args);},$name.' '.$method.' differential');
    }
    $field=array_key_first($edit);$value=reset($edit);
    foreach([$wpdb->prepare('UPDATE '.$table.' SET '.$field.'=%s WHERE '.$key.'=%d',$value,$id),'DELETE FROM '.$table.' WHERE '.$key.'='.$id] as $sql){
        hardening_compare(static function()use($sql){global $wpdb;return $wpdb->query($sql);},static function()use($sql){return Biawin_CRM_DB::write('query',$sql);},$name.' raw SQL differential');
    }
}
// False return/error metadata and a throwing database driver retain their original contract.
$args=['test_bcrm_phones',['id'=>$p,'normalized_phone'=>'09123456789','created_at'=>$now]];
hardening_compare(static function()use($args){global $wpdb;return $wpdb->insert(...$args);},static function()use($args){return Biawin_CRM_DB::write('insert',...$args);},'duplicate-key false return and last_error');
$wpdb->throw_write=true;
hardening_compare(static function()use($s){global $wpdb;return $wpdb->update('test_bcrm_customers',['display_name'=>'throw'],['id'=>$s]);},static function()use($s){return Biawin_CRM_DB::write('update','test_bcrm_customers',['display_name'=>'throw'],['id'=>$s]);},'driver exception propagation');
$wpdb->throw_write=false;
$wpdb=$saved_wpdb;unset($fixed_time,$fixed_rand);
