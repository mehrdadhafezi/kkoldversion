<?php
/** SQL-backed integration test double: SQLite executes DML; MySQL locks/engine metadata are simulated. */
define('ABSPATH',__DIR__.'/');
define('BIAWIN_CRM_VERSION','0.8.65.19');
$checks=0; $uid=1; $sms=[]; $deny_profiles=[]; $options=['biawin_crm_db_version'=>BIAWIN_CRM_VERSION];
function check($ok,$message) { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
function denied(callable $work,string $message): void { try { $work(); } catch (RuntimeException $e) { check(true,$message); return; } throw new RuntimeException('Expected denial: '.$message); }
function get_current_user_id() { global $uid; return $uid; }
function date_i18n($format) { return gmdate($format); }
function wp_rand($min=0,$max=2147483647) { global $fixed_rand; return $fixed_rand ?? random_int($min,$max); }
function current_time($format) { global $fixed_time; return $fixed_time ?? gmdate('Y-m-d H:i:s'); }
function get_option($name,$default=false) { global $options; return $options[$name] ?? $default; }
function wp_json_encode($value,$flags=0) { return json_encode($value,$flags); }
function sanitize_textarea_field($s) { return strip_tags((string)$s); }
function sanitize_text_field($s) { return strip_tags((string)$s); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/','',strtolower($s)); }
function get_user_meta($id,$key,$single) { return false; }
function apply_filters($name,$default,...$args) { global $sms,$sms_adapter; if ($name==='biawin_crm_ownership_send_otp') { if ($sms_adapter === false) return $default; $sms[]=$args; return true; } return $default; }
function get_post($id) { return (object)['post_type'=>'attachment','post_author'=>get_current_user_id()]; }
function get_attached_file($id) { return __FILE__; }
function get_post_mime_type($id) { return 'application/pdf'; }
class Biawin_CRM_Frontend { static function can_access_customer($id) { global $deny_profiles; return !in_array($id,$deny_profiles,true); } }
$role_caps=[];
function current_user_can($cap,...$args) { global $uid,$role_caps; return in_array($cap,$role_caps[$uid] ?? [],true); }
function get_user_by($key,$id) { return new class($id) { public $id; function __construct($id){$this->id=$id;} function has_cap($cap){ global $role_caps; return in_array($cap,$role_caps[$this->id]??[],true); } }; }
class SQLFixture {
    public $prefix='test_',$dbname='sqlite_test_double',$last_error='',$insert_id=0,$rows_affected=0,$queries=[],$fail_customer=0,$lock_available=true,$engine='InnoDB';
    public $pdo,$gate_calls=0,$calls=[],$throw_write=false;
    private $suppressed=false;
    function suppress_errors($value=true){$old=$this->suppressed;$this->suppressed=$value;return $old;}
    function __construct() { $this->pdo=new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    function prepare($sql,...$args) {
        if(count($args)===1 && is_array($args[0])) $args=$args[0];
        $i=0; return preg_replace_callback('/%[dsf]/',function($m)use(&$i,$args){$v=$args[$i++]; return $m[0]==='%d'?(string)(int)$v:($m[0]==='%f'?(string)(float)$v:$this->pdo->quote((string)$v));},$sql);
    }
    function query($sql) {
        if ($this->throw_write && preg_match('/^(INSERT|UPDATE|DELETE|REPLACE) /',$sql)) throw new RuntimeException('injected driver exception');
        $this->queries[]=$sql; $this->last_error='';
        try {
            if(strpos($sql,'RELEASE_LOCK')!==false) return 1;
            if($sql==='SET TRANSACTION READ WRITE'){if($this->pdo->inTransaction()){throw new RuntimeException('transaction already active');} return 0;}
            if($sql==='START TRANSACTION'){ $this->pdo->beginTransaction(); return 1; }
            if($sql==='COMMIT'){ $this->pdo->commit(); return 1; }
            if($sql==='ROLLBACK'){ if($this->pdo->inTransaction())$this->pdo->rollBack(); return 1; }
            if($this->fail_customer && preg_match('/^UPDATE `?test_bcrm_customers`? /',$sql) && strpos($sql,"`id` = '".$this->fail_customer."'")!==false) { $this->fail_customer=0; throw new RuntimeException('injected recalculation failure'); }
            $this->rows_affected=$this->pdo->exec(str_replace('INSERT IGNORE','INSERT OR IGNORE',$sql));
            if(preg_match('/^INSERT/i',$sql))$this->insert_id=(int)$this->pdo->lastInsertId();
            return $this->rows_affected;
        } catch(Throwable $e){$this->last_error=$e->getMessage(); return false;}
    }
    function get_results($sql) {
        $this->queries[]=$sql; $this->last_error='';
        if(strpos($sql,'SHOW TABLE STATUS')===0){ preg_match_all("/'([^']+)'/",$sql,$m); return array_map(function($name){return(object)['Name'=>$name,'Engine'=>$this->engine];},$m[1]); }
        try { $sql=preg_replace('/ FOR UPDATE\b/i','',$sql); $sql=str_replace('<=>',' IS ',$sql); return $this->pdo->query($sql)->fetchAll(PDO::FETCH_OBJ); }
        catch(Throwable $e){$this->last_error=$e->getMessage().' SQL: '.$sql; return null;}
    }
    function get_var($sql){if(strpos($sql,'GET_LOCK')!==false){$this->gate_calls++;return $this->lock_available?'1':'0';} if($sql==='SELECT @@autocommit')return 1; $r=$this->get_results($sql); return $r?(array_values((array)$r[0])[0]??null):null;}
    function get_row($sql){$r=$this->get_results($sql); return $r[0]??null;}
    function get_col($sql,$column=0){return array_map(static function($row)use($column){return array_values((array)$row)[$column];},$this->get_results($sql)??[]);}
    function insert($table,$data,$format=null){ $columns=implode(',',array_map(static function($key){return '`'.$key.'`';},array_keys($data)));$values=implode(',',array_map(function($v){return $v===null?'NULL':$this->pdo->quote((string)$v);},array_values($data)));return $this->query('INSERT INTO '.$table.' ('.$columns.') VALUES ('.$values.')'); }
    function update($table,$data,$where,$format=null,$where_format=null){$set=[];$parts=[];foreach($data as $k=>$v)$set[]='`'.$k.'` = '.($v===null?'NULL':$this->pdo->quote((string)$v));foreach($where as $k=>$v)$parts[]='`'.$k.'` = '.$this->pdo->quote((string)$v);return $this->query('UPDATE '.$table.' SET '.implode(',',$set).' WHERE '.implode(' AND ',$parts));}
    function delete($table,$where,$format=null){$parts=[];foreach($where as $k=>$v)$parts[]='`'.$k.'` = '.$this->pdo->quote((string)$v);return $this->query('DELETE FROM '.$table.' WHERE '.implode(' AND ',$parts));}
}
$wpdb=new SQLFixture();
foreach(['db','normalizer','ownership-schema','ownership-authorization','ownership-migration-audit','ownership-repository','ownership-audit','customer-hold-service','ownership-lock-service','invoice-phone-service','ownership-risk-service','ownership-workflow','ownership-otp','ownership-transfer-service','ownership-write-guard','customer-service','workflow-service','deficiency-workflow'] as $name) require_once __DIR__.'/../includes/class-biawin-crm-'.$name.'.php';
function fixture_schema(): void {
    global $wpdb;
    $source=file_get_contents(__DIR__.'/../includes/class-biawin-crm-activator.php');
    preg_match_all('/CREATE TABLE \{\$p\}(\w+) \((.*?)\n        \) \$charset/s',$source,$matches,PREG_SET_ORDER);
    $statements=[];foreach($matches as $m)$statements[]='CREATE TABLE test_bcrm_'.$m[1].' ('.$m[2]."\n)";
    $statements=array_merge($statements,Biawin_CRM_Ownership_Schema::tables('test_bcrm_',''));
    foreach($statements as $sql){
        $sql=preg_replace('/\) ENGINE=InnoDB.*$/s',')',$sql);
        $sql=preg_replace('/id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT/','id INTEGER PRIMARY KEY AUTOINCREMENT',$sql);
        $sql=preg_replace('/,?\s*PRIMARY KEY\s*\(id\)/','',$sql);
        $sql=preg_replace('/,\s*KEY \w+ \([^\n]*\)/','',$sql);
        $sql=preg_replace('/UNIQUE KEY \w+ /','UNIQUE ',$sql);
        $sql=preg_replace('/(product_name)\(120\)/','$1',$sql);
        if($wpdb->query($sql)===false)throw new RuntimeException($wpdb->last_error.' Schema: '.$sql);
    }
    foreach(['profile_completeness'=>"TEXT DEFAULT 'pending'",'completion_source'=>"TEXT DEFAULT 'NORMAL'",'journey_state'=>'TEXT','satisfaction_state'=>'TEXT','service_credit_total'=>'REAL DEFAULT 0','service_credit_reserved'=>'REAL DEFAULT 0','service_credit_consumed'=>'REAL DEFAULT 0','service_credit_remaining'=>'REAL DEFAULT 0'] as $column=>$type) check($wpdb->query('ALTER TABLE test_bcrm_customers ADD COLUMN '.$column.' '.$type)!==false,'fixture customer column '.$column);
    foreach(Biawin_CRM_Ownership_Schema::invoice_columns() as $column=>$type)check($wpdb->query('ALTER TABLE test_bcrm_invoices ADD COLUMN '.$column.' '.$type)!==false,'fixture invoice column '.$column);
}
fixture_schema();
$map=Biawin_CRM_Ownership_Authorization::role_capabilities();
$role_caps=[1=>$map['bcrm_agent'],2=>$map['bcrm_ownership_specialist'],3=>$map['bcrm_ownership_supervisor'],4=>$map['bcrm_ownership_specialist']];
function row($table,$id){return Biawin_CRM_Ownership_Repository::row($table,$id);}
function version($id){return(int)row('ownership_cases',$id)->lock_version;}
function add($table,$data){return Biawin_CRM_Ownership_Repository::insert($table,$data);}
function setup_pair(int $invoices=1): array {
    $now=Biawin_CRM_DB::now(); $source=add('customers',['internal_uuid'=>bin2hex(random_bytes(12)),'display_name'=>'Source','profile_completeness'=>'complete','total_paid'=>1000000*$invoices,'declared_paid'=>1000000*$invoices,'created_at'=>$now,'updated_at'=>$now]);
    $target=add('customers',['internal_uuid'=>bin2hex(random_bytes(12)),'display_name'=>'Target','created_at'=>$now,'updated_at'=>$now]);
    add('interactions',['agent_user_id'=>1,'type'=>'call','customer_id'=>$source,'declared_paid'=>1000000*$invoices,'created_at'=>$now]);
    add('interactions',['agent_user_id'=>1,'type'=>'call','customer_id'=>$target,'declared_paid'=>1000000*$invoices,'created_at'=>$now]);
    $phone=add('phones',['customer_id'=>$source,'raw_phone'=>'091'.str_pad((string)$source,8,'0',STR_PAD_LEFT),'normalized_phone'=>'091'.str_pad((string)$source,8,'0',STR_PAD_LEFT),'created_at'=>$now]);
    $ids=[];
    for($i=0;$i<$invoices;$i++){
        $id=add('invoices',['customer_id'=>$source,'invoice_code'=>bin2hex(random_bytes(6)),'amount_paid'=>1000000,'created_at'=>$now,'updated_at'=>$now]);$ids[]=$id;
        add('invoice_phones',['invoice_id'=>$id,'normalized_phone'=>row('phones',$phone)->normalized_phone,'created_at'=>$now]);
        add('services',['customer_id'=>$source,'invoice_id'=>$id,'service_name'=>'Service','created_at'=>$now]);
        add('payments',['invoice_id'=>$id,'amount'=>1000000,'created_at'=>$now]);
    }
    return [$source,$target,$phone,$ids];
}
function draft_case(array $pair,string $mode='KEEP_CURRENT_PHONE',bool $claim_phone=false,int $count=1,?int $target_phone=null,?string $proposed=null): int {
    global $uid; $uid=1; [$source,$target,$phone,$ids]=$pair;
    $case=Biawin_CRM_Ownership_Workflow::create_draft($source,$target,'claim','review');
    for($i=0;$i<$count;$i++)Biawin_CRM_Ownership_Workflow::add_draft_asset($case,version($case),['asset_type'=>'INVOICE','asset_id'=>$ids[$i],'phone_resolution'=>$mode,'phone_id'=>$target_phone,'phone'=>$proposed]);
    if($claim_phone)Biawin_CRM_Ownership_Workflow::add_draft_asset($case,version($case),['asset_type'=>'PHONE','asset_id'=>$phone]);
    return $case;
}
function reviewing(int $case): void {
    global $uid; $uid=1;Biawin_CRM_Ownership_Workflow::submit($case,version($case));
    $uid=3;Biawin_CRM_Ownership_Workflow::assign($case,version($case),2);
    $uid=2;Biawin_CRM_Ownership_Workflow::start_review($case,version($case));
    Biawin_CRM_Ownership_Workflow::add_evidence($case,version($case),1,'claimant_receipt','receipt');
}
function approve(int $case,bool $high=false):void {
    global $uid; $uid=2;Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','CLAIMANT_RECEIPT','reviewed');
    if($high){check(row('ownership_cases',$case)->status==='AWAITING_SUPERVISOR_APPROVAL','high-risk proposal waits');$uid=3;Biawin_CRM_Ownership_Workflow::supervisor_decision($case,version($case),true,'second approval');$uid=2;}
}
function owner_digest(array $pair):string{global $wpdb;[$s,$t,$p,$ids]=$pair;return json_encode([$wpdb->get_results('SELECT id,customer_id,canonical_phone_normalized FROM test_bcrm_invoices'),$wpdb->get_results('SELECT id,customer_id FROM test_bcrm_phones'),$wpdb->get_results('SELECT id,customer_id FROM test_bcrm_services'),$wpdb->get_results('SELECT id,profile_completeness FROM test_bcrm_customers')]);}
$pair=setup_pair(2);[$s,$t,$p,$ids]=$pair;$before=owner_digest($pair);
$case=draft_case($pair);check(owner_digest($pair)===$before,'draft changes no canonical data');
$uid=1;Biawin_CRM_Ownership_Workflow::submit($case,version($case));check(owner_digest($pair)===$before,'submit changes no canonical data');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_customer_holds WHERE case_id=$case AND status='ACTIVE'")===2,'both holds active');
denied(static function()use($s){Biawin_CRM_DB::write('update','test_bcrm_customers',['display_name'=>'bad'],['id'=>$s]);},'held profile update blocked');
denied(static function()use($ids){Biawin_CRM_DB::write('query',"UPDATE test_bcrm_invoices SET amount_paid=5 WHERE id=".$ids[0]);},'held raw import update blocked');
denied(static function()use($case){Biawin_CRM_Ownership_Workflow::start_review($case,version($case));},'requester cannot review');
$other=draft_case($pair);denied(static function()use($other){Biawin_CRM_Ownership_Workflow::submit($other,version($other));},'conflicting invoice submit blocked');
$uid=3;Biawin_CRM_Ownership_Workflow::assign($case,version($case),2);$uid=4;denied(static function()use($case){Biawin_CRM_Ownership_Workflow::start_review($case,version($case));},'unassigned specialist blocked');
$uid=2;Biawin_CRM_Ownership_Workflow::start_review($case,version($case));Biawin_CRM_Ownership_Workflow::add_evidence($case,version($case),1,'claimant_receipt');
$uid=1;denied(static function()use($case){Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','CLAIMANT_RECEIPT','bad');},'requester cannot approve');
$payments=$wpdb->get_results('SELECT * FROM test_bcrm_payments');approve($case);$uid=2;Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));
check(row('ownership_cases',$case)->status==='CLOSED','normal case closes');check((int)row('invoices',$ids[0])->customer_id===$t,'selected invoice moves');check((int)row('invoices',$ids[1])->customer_id===$s,'sibling invoice stays');check((int)row('phones',$p)->customer_id===$s,'KEEP phone owner stays');
check(row('invoices',$ids[0])->canonical_phone_normalized===row('phones',$p)->normalized_phone,'canonical current phone set');
check($wpdb->get_results('SELECT * FROM test_bcrm_payments')==$payments,'payment history unchanged');
check((int)$wpdb->get_var('SELECT customer_id FROM test_bcrm_services WHERE invoice_id='.$ids[0])===$t,'service follows invoice');
check(row('customers',$s)->profile_completeness==='incomplete','source recalculated incomplete');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM test_bcrm_deficiency_cases WHERE customer_id='.$s)>0,'normal deficiency routing');
check((float)row('customers',$t)->total_paid===1000000.0,'target recalculated');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_customer_holds WHERE case_id=$case AND status='ACTIVE'")===0,'holds released safely');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM test_bcrm_customers WHERE id='.$s)===1,'source never collapsed');
denied(static function()use($case){Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));},'cannot execute twice');
Biawin_CRM_Ownership_Transfer_Service::resume($case,version($case));check(row('ownership_cases',$case)->status==='CLOSED','recovery after finalization idempotent');
$uid=2;denied(static function()use($ids,$s){Biawin_CRM_Customer_Service::attach_invoice($s,row('invoices',$ids[0])->invoice_code,'bypass',false);},'locked invoice authoritative attach blocked');
$unheld=setup_pair();[$us,$ut,$up,$ui]=$unheld;Biawin_CRM_Ownership_Repository::update('customers',['profile_completeness'=>'pending'],['id'=>$us]);
check(Biawin_CRM_DB::write('update','test_bcrm_customers',['display_name'=>'Allowed'],['id'=>$us])===1,'unheld write unchanged');check(row('customers',$us)->display_name==='Allowed','unheld data updated');
$uid=2;$result=Biawin_CRM_Customer_Service::attach_invoice($ut,row('invoices',$ui[0])->invoice_code,'baseline',false);check($result['ok'],'unlocked attach baseline behavior');
// All remaining phone resolution modes and high-risk second approval.
foreach(['USE_TARGET_PROFILE_PHONE','CLAIM_CURRENT_PHONE','PROPOSE_NEW_PHONE'] as $mode){
    $pair=setup_pair(2);[$s,$t,$p,$ids]=$pair;$tp=null;$proposed=null;
    if($mode==='USE_TARGET_PROFILE_PHONE')$tp=add('phones',['customer_id'=>$t,'raw_phone'=>'092'.str_pad((string)$t,8,'0',STR_PAD_LEFT),'normalized_phone'=>'092'.str_pad((string)$t,8,'0',STR_PAD_LEFT),'created_at'=>Biawin_CRM_DB::now()]);
    if($mode==='PROPOSE_NEW_PHONE')$proposed='093'.str_pad((string)$t,8,'0',STR_PAD_LEFT);
    $case=draft_case($pair,$mode,$mode==='CLAIM_CURRENT_PHONE',1,$tp,$proposed);reviewing($case);
    if($mode==='CLAIM_CURRENT_PHONE'){
        check(row('ownership_cases',$case)->risk_level==='HIGH_RISK','phone transfer high risk');
        $uid=2;$challenge=Biawin_CRM_Ownership_OTP::issue($case,version($case),$p);$code=$sms[count($sms)-1][1];
        check(strpos(row('ownership_otp_challenges',$challenge)->otp_hash,$code)===false,'OTP plaintext not stored');
        denied(static function()use($case,$challenge,$p,$code){Biawin_CRM_Ownership_OTP::verify($case,version($case),$challenge,$p,'CONFIRM_TRANSFER_CONSENT',$code);},'OTP purpose scope');
        $digest=owner_digest($pair);check(Biawin_CRM_Ownership_OTP::verify($case,version($case),$challenge,$p,'CONFIRM_PHONE_CONTROL',$code),'OTP verification');check(owner_digest($pair)===$digest,'OTP no transfer');
        check(!Biawin_CRM_Ownership_OTP::verify($case,version($case),$challenge,$p,'CONFIRM_PHONE_CONTROL',$code),'OTP cannot reuse');
        $uid=2;Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','CLAIMANT_RECEIPT','reviewed');
        denied(static function()use($case){Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));},'high risk cannot self execute');
        $uid=3;Biawin_CRM_Ownership_Workflow::supervisor_decision($case,version($case),false,'need more evidence');check(row('ownership_cases',$case)->status==='UNDER_REVIEW','supervisor rejection returns review');
        approve($case,true);
    }else approve($case);
    $uid=2;Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));
    check((int)row('invoices',$ids[0])->customer_id===$t,$mode.' invoice moves');check((int)row('invoices',$ids[1])->customer_id===$s,$mode.' sibling stays');
    if($mode==='CLAIM_CURRENT_PHONE')check((int)row('phones',$p)->customer_id===$t,'explicit phone owner moves');
    else check((int)row('phones',$p)->customer_id===$s,'old phone owner retained');
    check((int)$wpdb->get_var('SELECT COUNT(*) FROM test_bcrm_invoice_phones WHERE invoice_id='.$ids[0])===($mode==='CLAIM_CURRENT_PHONE'?1:2),'phone history preserved');
}
// Multi-invoice risk, amendments, mandatory override reason and sticky escalation.
$pair=setup_pair(2);$case=draft_case($pair,'KEEP_CURRENT_PHONE',false,2);reviewing($case);check(row('ownership_cases',$case)->risk_level==='HIGH_RISK','multiple invoices high risk');
$uid=2;Biawin_CRM_Ownership_Workflow::decide($case,version($case),'CLAIM_REJECTED','DOCUMENT_REVIEW','insufficient evidence');check(row('ownership_cases',$case)->status==='CLOSED','rejection closes without transfer');check((int)row('invoices',$pair[3][0])->customer_id===$pair[0],'rejection ownership unchanged');
$pair=setup_pair(2);$case=draft_case($pair);reviewing($case);$uid=2;Biawin_CRM_Ownership_Workflow::amend($case,version($case),[['asset_type'=>'INVOICE','asset_id'=>$pair[3][1],'phone_resolution'=>'KEEP_CURRENT_PHONE']],'expanded evidence');check(row('ownership_cases',$case)->risk_level==='HIGH_RISK','amendment high risk');
Biawin_CRM_Ownership_Workflow::evaluate_risk($case,version($case));check(row('ownership_cases',$case)->risk_level==='HIGH_RISK','risk cannot downgrade');Biawin_CRM_Ownership_Workflow::decide($case,version($case),'CLAIM_WITHDRAWN','DOCUMENT_REVIEW','claimant withdrew');check((int)row('invoices',$pair[3][0])->customer_id===$pair[0],'withdrawal ownership unchanged');
$pair=setup_pair();$case=draft_case($pair);reviewing($case);$asset=Biawin_CRM_Ownership_Repository::assets($case)[0];
denied(static function()use($case,$asset){Biawin_CRM_Ownership_Workflow::set_final_plan($case,version($case),(int)$asset->id,['phone_resolution'=>'PROPOSE_NEW_PHONE','phone'=>'09900000000'],'');},'override reason mandatory');
Biawin_CRM_Ownership_Workflow::set_final_plan($case,version($case),(int)$asset->id,['phone_resolution'=>'PROPOSE_NEW_PHONE','phone'=>'09900000000'],'new contact evidence');check(row('ownership_cases',$case)->risk_level==='HIGH_RISK','override escalates');
$uid=3;Biawin_CRM_Ownership_Workflow::cancel($case,version($case),'cancel safely');check(row('ownership_cases',$case)->status==='CANCELLED','audited cancellation');
// Post-commit failure and atomic, idempotent recovery.
$pair=setup_pair();$case=draft_case($pair);reviewing($case);approve($case);$uid=2;$wpdb->fail_customer=$pair[0];
denied(static function()use($case){Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));},'postcommit failure surfaces');
check(row('ownership_cases',$case)->execution_state==='FINALIZATION_FAILED','durable failure state');check((int)row('invoices',$pair[3][0])->customer_id===$pair[1],'committed ownership not reversed');check((int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_customer_holds WHERE case_id=$case AND status='ACTIVE'")===2,'failure keeps both holds');
$events=(int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_ownership_events WHERE case_id=$case AND event_type='INVOICE_OWNER_CHANGED'");Biawin_CRM_Ownership_Transfer_Service::resume($case,version($case));check(row('ownership_cases',$case)->status==='CLOSED','recovery completes');check((int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_ownership_events WHERE case_id=$case AND event_type='INVOICE_OWNER_CHANGED'")===$events,'recovery no duplicate transfer');
$pair=setup_pair();$case=draft_case($pair);reviewing($case);approve($case);$uid=2;Biawin_CRM_Ownership_Repository::update('invoices',['amount_paid'=>2000000],['id'=>$pair[3][0]]);
denied(static function()use($case){Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));},'stale invoice snapshot blocked');check((int)row('invoices',$pair[3][0])->customer_id===$pair[0],'stale execution ownership untouched');
$wpdb->lock_available=false;denied(static function(){Biawin_CRM_Ownership_Repository::atomic(static function(){});},'unavailable concurrency lock fails closed');$wpdb->lock_available=true;
// Raw SQL forms used by imports, plus transaction gate lifetime.
$pair=setup_pair();[$s,$t,$p,$ids]=$pair;
Biawin_CRM_Ownership_Repository::update('customers',['profile_completeness'=>'pending'],['id'=>$s]);
check(Biawin_CRM_DB::write('query',"UPDATE test_bcrm_invoices SET amount_paid=amount_paid+1 WHERE id=".$ids[0])===1,'unheld raw expression update proceeds');
Biawin_CRM_DB::write('query','START TRANSACTION');
Biawin_CRM_DB::write('update','test_bcrm_customers',['display_name'=>'transaction'],['id'=>$s]);
denied(static function(){Biawin_CRM_Ownership_Repository::atomic(static function(){});},'cannot nest ownership command in legacy transaction');
Biawin_CRM_DB::write('query','ROLLBACK');check(row('customers',$s)->display_name==='Source','legacy rollback preserved');
Biawin_CRM_DB::write('query','START TRANSACTION');Biawin_CRM_DB::write('update','test_bcrm_customers',['display_name'=>'transaction'],['id'=>$s]);Biawin_CRM_DB::write('query','COMMIT');check(row('customers',$s)->display_name==='transaction','legacy commit preserved');
$case=draft_case($pair);reviewing($case);$uid=2;
foreach(['add_phone','create_manual_invoice','detach_phone_to_new_customer'] as $operation){
    $before_count=(int)$wpdb->get_var('SELECT COUNT(*) FROM test_bcrm_customers');
    denied(static function()use($operation,$s,$p){
        if($operation==='add_phone')Biawin_CRM_Customer_Service::add_phone($s,'09911111111','unverified','self');
        elseif($operation==='create_manual_invoice')Biawin_CRM_Customer_Service::create_manual_invoice($s,'denied',1);
        else Biawin_CRM_Customer_Service::detach_phone_to_new_customer($s,$p);
    },'held authoritative '.$operation);
    check((int)$wpdb->get_var('SELECT COUNT(*) FROM test_bcrm_customers')===$before_count,'held operation leaves no new profile');
}
denied(static function()use($s,$p){Biawin_CRM_DB::write('query',"INSERT INTO test_bcrm_phones (customer_id,raw_phone,normalized_phone,source,is_primary,created_at) VALUES ($s,'09911111111','09911111111','import',0,'2026-10-21 00:00:00') ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id)");},'held raw import upsert denied');
denied(static function()use($ids){Biawin_CRM_DB::write('delete','test_bcrm_payments',['invoice_id'=>$ids[0]]);},'held payment deletion denied');
denied(static function()use($s){Biawin_CRM_DB::write('insert','test_bcrm_assignments',['customer_id'=>$s,'agent_user_id'=>1,'assigned_at'=>Biawin_CRM_DB::now()]);},'held assignment insert denied');
$def=add('deficiency_cases',['customer_id'=>$s,'case_code'=>'held-case','status'=>'UNDER_REVIEW','created_at'=>Biawin_CRM_DB::now(),'updated_at'=>Biawin_CRM_DB::now()]);
denied(static function()use($def){Biawin_CRM_DB::write('update','test_bcrm_deficiency_cases',['status'=>'COMPLETED'],['id'=>$def]);},'held deficiency transition denied');check(row('deficiency_cases',$def)->status==='UNDER_REVIEW','held workflow state preserved');
check(Biawin_CRM_Ownership_Write_Guard::protect_media(null,(object)['ID'=>1],true)===false,'ownership evidence media cannot be deleted');
$uid=1;$own=Biawin_CRM_Ownership_Workflow::get($case);check(!isset($own['case']->snapshot_json),'requester receives no internal profile snapshot');$uid=4;denied(static function()use($case){Biawin_CRM_Ownership_Workflow::get($case);},'other specialist cannot view case');
$uid=3;Biawin_CRM_Ownership_Workflow::cancel($case,version($case),'operator cancellation');
// A lock survives later incompleteness, independent of a case or current completeness.
Biawin_CRM_Ownership_Repository::update('customers',['profile_completeness'=>'complete'],['id'=>$s]);Biawin_CRM_Ownership_Lock_Service::sync_completed($s);
$lock_count=(int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_ownership_locks WHERE entity_type='INVOICE' AND entity_id=".$ids[0].' AND released_at IS NULL');
Biawin_CRM_Ownership_Repository::update('customers',['profile_completeness'=>'incomplete'],['id'=>$s]);Biawin_CRM_Ownership_Lock_Service::sync_completed($s);
check((int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_ownership_locks WHERE entity_type='INVOICE' AND entity_id=".$ids[0].' AND released_at IS NULL')===$lock_count && $lock_count===1,'persistent invoice lock survives incompleteness');
denied(static function()use($ids,$t){Biawin_CRM_DB::write('query','UPDATE test_bcrm_invoices SET customer_id='.$t.' WHERE id='.$ids[0]);},'raw locked owner update denied');
// OTP expiry, limits, resend invalidation, exact phone scope and version checks.
$pair=setup_pair();$case=draft_case($pair,'CLAIM_CURRENT_PHONE',true);reviewing($case);$uid=2;$phone=$pair[2];
$challenge=Biawin_CRM_Ownership_OTP::issue($case,version($case),$phone);$code=$sms[count($sms)-1][1];
denied(static function()use($case,$phone){Biawin_CRM_Ownership_OTP::issue($case,version($case),$phone);},'OTP resend throttled');
Biawin_CRM_Ownership_Repository::update('ownership_otp_challenges',['expires_at'=>'2000-01-01 00:00:00'],['id'=>$challenge]);
check(!Biawin_CRM_Ownership_OTP::verify($case,version($case),$challenge,$phone,'CONFIRM_PHONE_CONTROL',$code),'expired OTP rejected');
check(row('ownership_cases',$case)->risk_level==='HIGH_RISK','OTP failure risk sticky');
Biawin_CRM_Ownership_Repository::update('ownership_otp_challenges',['sent_at'=>'2000-01-01 00:00:00'],['id'=>$challenge]);$challenge2=Biawin_CRM_Ownership_OTP::issue($case,version($case),$phone);$right=$sms[count($sms)-1][1];$wrong=$right==='111111'?'222222':'111111';
for($i=0;$i<5;$i++)check(!Biawin_CRM_Ownership_OTP::verify($case,version($case),$challenge2,$phone,'CONFIRM_PHONE_CONTROL',$wrong),'OTP incorrect attempt '.$i);
check((int)row('ownership_otp_challenges',$challenge2)->attempt_count===5,'attempt count persisted');check(!Biawin_CRM_Ownership_OTP::verify($case,version($case),$challenge2,$phone,'CONFIRM_PHONE_CONTROL',$right),'correct OTP rejected after limit');
denied(static function()use($case,$challenge2,$phone,$right){Biawin_CRM_Ownership_OTP::verify($case,version($case),$challenge2,$phone+1000,'CONFIRM_PHONE_CONTROL',$right);},'OTP exact disputed phone enforced');
$stale=version($case);Biawin_CRM_Ownership_Workflow::record_contact($case,$stale,'follow_up','contact note');denied(static function()use($case,$stale){Biawin_CRM_Ownership_Workflow::start_review($case,$stale);},'optimistic stale version denied');
// A supervisor who proposed the decision cannot act as their own second approver.
$uid=3;Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','CLAIMANT_RECEIPT','proposed');denied(static function()use($case){Biawin_CRM_Ownership_Workflow::supervisor_decision($case,version($case),true,'self approve');},'same actor second approval denied');Biawin_CRM_Ownership_Workflow::cancel($case,version($case),'end case');
// Rollback a transfer after its first canonical write, before commit.
$pair=setup_pair();$case=draft_case($pair);reviewing($case);approve($case);$uid=2;
$wpdb->pdo->exec("CREATE TRIGGER fail_transfer BEFORE UPDATE ON test_bcrm_services WHEN OLD.invoice_id=".$pair[3][0]." BEGIN SELECT RAISE(ABORT,'injected alignment failure'); END");
denied(static function()use($case){Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));},'precommit failure rolls back all ownership writes');
check((int)row('invoices',$pair[3][0])->customer_id===$pair[0],'invoice rollback after failed service alignment');check(row('ownership_cases',$case)->execution_state==='NOT_STARTED','execution marker rolls back');$wpdb->pdo->exec('DROP TRIGGER fail_transfer');Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));check(row('ownership_cases',$case)->status==='CLOSED','retry after precommit failure succeeds');
// Phone-only transfer never moves any invoice that happens to share that phone.
$pair=setup_pair(2);$uid=1;$case=Biawin_CRM_Ownership_Workflow::create_draft($pair[0],$pair[1]);Biawin_CRM_Ownership_Workflow::add_draft_asset($case,version($case),['asset_type'=>'PHONE','asset_id'=>$pair[2]]);reviewing($case);approve($case,true);$uid=2;Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));check((int)row('phones',$pair[2])->customer_id===$pair[1],'phone-only transfer');foreach($pair[3] as $invoice)check((int)row('invoices',$invoice)->customer_id===$pair[0],'phone-only transfer leaves unrelated invoice');
// Compare an ordinary attach with ownership guards disabled/enabled using equivalent fixtures.
$outcomes=[];foreach(['0.8.65.18','0.8.65.19'] as $db_version){$pair=setup_pair();[$s,$t,$p,$ids]=$pair;Biawin_CRM_Ownership_Repository::update('customers',['profile_completeness'=>'pending'],['id'=>$s]);$options['biawin_crm_db_version']=$db_version;$uid=2;$result=Biawin_CRM_Customer_Service::attach_invoice($t,row('invoices',$ids[0])->invoice_code,'same request',false);$outcomes[]=[$result['ok'],row('customers',$s)->profile_completeness,row('customers',$t)->profile_completeness,(float)row('customers',$s)->total_paid,(float)row('customers',$t)->total_paid,(int)row('invoices',$ids[0])->customer_id===$t];}check($outcomes[0]===$outcomes[1],'unheld/unlocked attach baseline-equivalent with guards on/off');$options['biawin_crm_db_version']=BIAWIN_CRM_VERSION;
// Different assets may have concurrent cases on the same profiles. Releasing
// one case must not remove another case's hold.
$pair=setup_pair(2);$case1=draft_case($pair);$uid=1;Biawin_CRM_Ownership_Workflow::submit($case1,version($case1));
$case2=Biawin_CRM_Ownership_Workflow::create_draft($pair[0],$pair[1]);Biawin_CRM_Ownership_Workflow::add_draft_asset($case2,version($case2),['asset_type'=>'INVOICE','asset_id'=>$pair[3][1],'phone_resolution'=>'KEEP_CURRENT_PHONE']);Biawin_CRM_Ownership_Workflow::submit($case2,version($case2));
check((int)$wpdb->get_var('SELECT COUNT(*) FROM test_bcrm_customer_holds WHERE customer_id='.$pair[0]." AND status='ACTIVE'")===2,'separate asset cases retain separate holds');$uid=3;Biawin_CRM_Ownership_Workflow::cancel($case1,version($case1),'cancel one');
denied(static function()use($pair){Biawin_CRM_DB::write('update','test_bcrm_customers',['display_name'=>'blocked'],['id'=>$pair[0]]);},'other active case still holds profile');Biawin_CRM_Ownership_Workflow::cancel($case2,version($case2),'cancel last');check(Biawin_CRM_DB::write('update','test_bcrm_customers',['display_name'=>'released'],['id'=>$pair[0]])===1,'last hold release restores ordinary writes');
// A transaction opened by an external caller is never implicitly committed.
$wpdb->query('START TRANSACTION');denied(static function(){Biawin_CRM_Ownership_Repository::atomic(static function(){});},'external transaction nesting rejected without commit');check($wpdb->pdo->inTransaction(),'external transaction remains active');$wpdb->query('ROLLBACK');
// Review-only is explicit: selecting an asset is not itself a transfer order.
$pair=setup_pair(2);$case=draft_case($pair);$uid=1;Biawin_CRM_Ownership_Workflow::add_draft_asset($case,version($case),['asset_type'=>'INVOICE','asset_id'=>$pair[3][1],'requested_action'=>'REVIEW_ONLY','phone_resolution'=>'KEEP_CURRENT_PHONE']);reviewing($case);approve($case,true);$uid=2;Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));check((int)row('invoices',$pair[3][1])->customer_id===$pair[0],'review-only asset not transferred');
denied(static function()use($case){Biawin_CRM_Ownership_Workflow::add_evidence($case,version($case),1,'claimant_receipt');},'decided evidence immutable');
$pair=setup_pair();$case=draft_case($pair);reviewing($case);$uid=2;denied(static function()use($case){Biawin_CRM_Ownership_OTP::issue($case,version($case),99999);},'invoice-only case cannot issue OTP');
denied(static function()use($case){Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','CURRENT_OWNER_CONSENT','consent');},'consent approval requires contact evidence');Biawin_CRM_Ownership_Workflow::record_contact($case,version($case),'current_owner_consent','current owner confirmed');Biawin_CRM_Ownership_Workflow::decide($case,version($case),'TRANSFER_APPROVED','CURRENT_OWNER_CONSENT','consent recorded');Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));check(row('ownership_cases',$case)->status==='CLOSED','audited consent approval');
$wpdb->engine='MyISAM';denied(static function(){Biawin_CRM_Ownership_Repository::atomic(static function(){});},'nontransactional tables fail closed');$wpdb->engine='InnoDB';
// Submission failure cannot leave half a hold or a submitted state.
$pair=setup_pair();$case=draft_case($pair);$uid=1;$wpdb->pdo->exec("CREATE TRIGGER fail_submit BEFORE INSERT ON test_bcrm_ownership_events WHEN NEW.event_type='CASE_SUBMITTED' BEGIN SELECT RAISE(ABORT,'injected submit failure'); END");denied(static function()use($case){Biawin_CRM_Ownership_Workflow::submit($case,version($case));},'submit failure rolls back');check(row('ownership_cases',$case)->status==='DRAFT','failed submit remains draft');check((int)$wpdb->get_var('SELECT COUNT(*) FROM test_bcrm_customer_holds WHERE case_id='.$case)===0,'failed submit leaves no holds');$wpdb->pdo->exec('DROP TRIGGER fail_submit');
$uid=1;Biawin_CRM_Ownership_Workflow::submit($case,version($case));$refreshed=false;Biawin_CRM_DB::read_refresh($pair[0],static function()use(&$refreshed){$refreshed=true;});check(!$refreshed,'held read surface skips refresh writes');$uid=3;Biawin_CRM_Ownership_Workflow::cancel($case,version($case),'end read test');Biawin_CRM_DB::read_refresh($pair[0],static function()use(&$refreshed){$refreshed=true;});check($refreshed,'unheld read surface preserves original refresh');
// Persistent locks also protect the binding/phone identity from indirect bypass.
$locked=$wpdb->get_row("SELECT l.entity_id,p.normalized_phone FROM test_bcrm_ownership_locks l JOIN test_bcrm_phones p ON p.id=l.entity_id WHERE l.entity_type='PHONE' AND l.released_at IS NULL LIMIT 1");
denied(static function()use($locked){Biawin_CRM_DB::write('update','test_bcrm_phones',['normalized_phone'=>'09922222222'],['id'=>(int)$locked->entity_id]);},'locked phone identity rename blocked');
$locked_invoice=$wpdb->get_row("SELECT l.entity_id,ip.id,ip.normalized_phone,i.customer_id FROM test_bcrm_ownership_locks l JOIN test_bcrm_invoices i ON i.id=l.entity_id JOIN test_bcrm_invoice_phones ip ON ip.invoice_id=i.id WHERE l.entity_type='INVOICE' AND l.released_at IS NULL LIMIT 1");
denied(static function()use($locked_invoice){Biawin_CRM_DB::write('delete','test_bcrm_invoice_phones',['id'=>(int)$locked_invoice->id]);},'locked invoice phone history deletion blocked');
check(Biawin_CRM_DB::write('update','test_bcrm_invoice_phones',['phone_position'=>1],['id'=>(int)$locked_invoice->id])===1,'nonbinding phone metadata update remains allowed');
$pair=setup_pair();$case=draft_case($pair);$uid=1;$service=$wpdb->get_row('SELECT id FROM test_bcrm_services WHERE invoice_id='.$pair[3][0]);Biawin_CRM_Ownership_Repository::update('services',['customer_id'=>$pair[1]],['id'=>(int)$service->id]);denied(static function()use($case){Biawin_CRM_Ownership_Workflow::submit($case,version($case));},'ambiguous legacy service ownership not silently repaired');
$pair=setup_pair();$newphone=add('phones',['customer_id'=>$pair[0],'raw_phone'=>'09933333333','normalized_phone'=>'09933333333','created_at'=>Biawin_CRM_DB::now()]);$case=draft_case($pair,'PROPOSE_NEW_PHONE',false,1,null,'09933333333');$uid=1;Biawin_CRM_Ownership_Workflow::add_draft_asset($case,version($case),['asset_type'=>'PHONE','asset_id'=>$newphone]);reviewing($case);approve($case,true);$uid=2;Biawin_CRM_Ownership_Transfer_Service::execute($case,version($case));check((int)row('phones',$newphone)->customer_id===$pair[1],'proposed source-owned phone requires and follows explicit approved claim');check((int)row('phones',$pair[2])->customer_id===$pair[0],'proposed replacement leaves original phone owner unchanged');
require __DIR__.'/ownership-c2-hardening.php';
echo "PASS: $checks SQL-backed C2 checks (SQLite/WordPress test doubles; MySQL and browser unverified).\n";
