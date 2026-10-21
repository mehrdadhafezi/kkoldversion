=== Biawin CRM Customer Integration ===
Contributors: biawin
Tags: crm, customer, invoices, satisfaction, complaints, workflow
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.8.64.1

== توضیحات ==

سامانه یکپارچه‌سازی مشتریان، تجمیع فاکتورها، ساخت پروفایل 360 درجه، رضایت‌سنجی، تطبیق مالی، شکایت، عودت وجه و تخصیص خدمات.

== قابلیت‌های نسخه 0.5.0 ==

* Import CSV/XLSX با Dry Run و تجمیع ردیف‌های یک کد فاکتور.
* کد فاکتور به‌عنوان شناسه یکتای فاکتور و نگهداری چند محصول زیر همان فاکتور.
* نرمال‌سازی شماره موبایل و نگهداری شماره‌های مرتبط با خود فاکتور.
* عملیات واحد «شماره + فاکتور» برای اتصال یا ثبت اطلاعات تکمیلی.
* ساخت پروفایل کامل/ناقص براساس مبلغ اعلامی مشتری و مجموع فاکتورها.
* کارتابل تأیید مالی و پیوست فیش/مدرک.
* پرتال کارشناسان با ورود نام کاربری/رمز عبور تعیین‌شده توسط مدیر.
* نقش‌های تماس، سرپرست، مالی، شکایت، خدمات و عودت.
* تخصیص پرونده مشتری به کارشناس و توزیع مساوی.
* رضایت‌سنجی چندشاخصی.
* مسیرهای بدون درخواست، شکایت و درخواست خدمات.
* سه خروجی شکایت: رضایت، درخواست خدمت، عودت.
* محاسبه اعتبار خدمات با ضریب، کف و سقف قابل تنظیم.
* انتخاب خدمات با جلوگیری از عبور از سقف اعتبار.
* پرونده تخصصی برای هر خدمت و تخصیص به کارشناس خدمات.
* گردش عودت وجه.
* Timeline و Audit Log.
* گزارش مدیریتی و CSV UTF-8.

== نصب ==

1. فایل ZIP را از افزونه‌ها > افزودن > بارگذاری افزونه نصب کنید.
2. افزونه را فعال کنید.
3. صفحه «پرتال CRM بیاوین» به‌صورت خودکار ساخته می‌شود.
4. از CRM بیاوین > کاربران پرتال، نام کاربری و رمز کارشناسان را بسازید.
5. اطلاعات اولیه را از بخش ایمپورت با Dry Run وارد کنید.

== ارتقا ==

ارتقا از 0.3.x و 0.4.x با migration داخلی انجام می‌شود. قبل از ارتقا Backup بگیرید.

== Changelog ==

= 0.8.64.1 =
* FIX — در «واحدهای قابل استعلام» دیگر وضعیت فعال/غیرفعال خود واحد قابل تغییر نیست؛ «استعلام‌پذیر» تنظیمی جداست و
  ذخیره آن فقط ستون‌های استعلام را تغییر می‌دهد (نام، والد، سرپرست، اعضا، ترتیب و وضعیت واحد دست نمی‌خورند).
* پاسخ‌دهندگان استعلام صریح شد: کارشناسان واحد / سرپرست واحد / کارشناسان + سرپرست (انتخاب تکی).
* واحد غیرفعال حتی اگر استعلام‌پذیر باشد استعلام دریافت نمی‌کند. بدون تغییر دیتابیس و بدون مهاجرت.

= 0.8.64.0 =
* NEW — صفحه «تنظیمات نارضایتی»: موضوعات نارضایتی، نتایج/تصمیمات نارضایتی و واحدهای قابل استعلام؛ با جستجو، افزودن،
  ویرایش، فعال/غیرفعال و ترتیب. هیچ موردی حذف فیزیکی نمی‌شود و سوابق قبلی همان عنوان اصلی را نشان می‌دهند.
* فرم ثبت نارضایتی در پروفایل مشتری، موضوع را از فهرست تعریف‌شده توسط مدیر می‌گیرد (تا وقتی موضوعی تعریف نشده، مثل قبل متن آزاد).
* فرم تعیین تکلیف، عنوان/ترتیب/فعال‌بودن نتیجه‌ها را از تنظیمات می‌خواند؛ نتیجه غیرفعال پذیرفته نمی‌شود.
* واحدهای قابل استعلام همان واحدهای موجود هستند (+ تعیین پاسخ‌دهنده: سرپرست یا همه اعضا). ساختار داده استعلام پرتالی و
  دستی و چند استعلام هم‌زمان برای یک پرونده آماده شد (رابط کاربری آن در فاز بعد).
* دسترسی جدید manage_inquiry_units فقط برای مدیر کل و مدیر پرتال؛ هیچ کاربر، رمز، نقش، خدمت یا واحدی تغییر نمی‌کند.

= 0.8.63.2 =
* FIX (خطای واقعی سرور) — خروجی اکسل مشتریان با «Call to undefined function disk_free_space()» از کار می‌افتاد
  (تابع روی هاست غیرفعال است). حالا بررسی فضای دیسک امن است و اگر ممکن نباشد خروجی بدون خطا ادامه می‌یابد.
* FIX (خطای واقعی سرور) — «No space left on device»: همه نوشتن‌ها بررسی می‌شوند؛ اگر فضا نباشد خروجی مشتریان و
  عودت به CSV برمی‌گردد، خروجی کارتابل پیام فارسی روشن می‌دهد، و ورود اکسل فایل ناقص را وارد نمی‌کند.
* فایل‌های موقت در پوشه موقت وردپرس (نه uploads) ساخته و در موفقیت، خطا و پایان درخواست پاک می‌شوند.
* بک‌آپ: فقط ۳ نسخه آخر نگه داشته می‌شود (BCRM_BACKUP_KEEP)، محل قابل تنظیم (BCRM_BACKUP_DIR)، فایل ناقص حذف می‌شود.
* مهاجرت دیتابیس با قفل اتمی MySQL؛ در صورت خطا نسخه جلو نمی‌رود و درخواست بعدی دوباره تلاش می‌کند.

= 0.8.63.0 =
* SECURITY/S4 — به‌روزرسانی افزونه دیگر هیچ کاربری نمی‌سازد، حساب حذف‌شده را برنمی‌گرداند، رمز تعیین نمی‌کند و
  نقش/نام/وضعیت غیرفعال کاربران را تغییر نمی‌دهد. سازنده‌های خودکار «کاربران پیش‌فرض» (با رمز ثابت) حذف شدند.
* S4 — همگام‌سازی نقش‌ها/دسترسی‌ها افزایشی و دفتر-محور شد و دیگر در هر بارگذاری صفحه اجرا نمی‌شود: دسترسی
  حذف‌شده توسط مدیر برنمی‌گردد، نقش حذف‌شده دوباره ساخته نمی‌شود، دسترسی‌های سفارشی حفظ می‌شوند.
* L2 — کاتالوگ خدمات و واحدها با به‌روزرسانی بازنویسی نمی‌شوند؛ پیش‌فرض‌ها فقط یک‌بار و فقط اگر نبودند درج می‌شوند.
* NEW — پایه خدمات پس از فروش: سلسله‌مراتب واحدها روی همان جدول واحدها، واحدهای اولیه (نارضایتی‌ها، خدمات،
  تأیید مالی، وام، خزانه)، نقش‌های مدیر خدمات پس از فروش/وام/خزانه، ۲۱ دسترسی پایه، صفحه «واحدها و اعضا».
* FIX — جدول refund_registers روی نصب تازه ساخته نمی‌شد (CREATE نامعتبر).

= 0.8.62.28 =
* NEW — بررسی فیش مالی به سبک پنل مالی فروش‌نت: با «بررسی و تأیید/رد» (روی هر کارت فیش، بالای فهرست فیش‌ها و
  در صف پرونده‌های مالی) پنجره‌ای باز می‌شود که فیش (تصویر با بزرگ‌نمایی/چرخش، PDF، یا لینک دانلود برای
  HEIC و فرمت‌های دیگر) را کنار اطلاعات کامل پرونده نشان می‌دهد و همان‌جا: تأیید همان فیش با مبلغ، رد با
  دلیل، بازگردانی به «بررسی‌نشده»، رفتن به فیش قبلی/بعدی (خودکار به فیش بررسی‌نشده بعدی)، سوابق پرونده
  و ثبت نتیجه نهایی مالی (با/بدون فاکتور/عدم تأیید) — همه بدون رفرش.
* وضعیت بررسی هر فیش (بررسی‌نشده/تأییدشده/ردشده) روی کارت‌ها دیده می‌شود، در Timeline ثبت می‌شود و خلاصه‌اش
  همراه نتیجه نهایی مالی ذخیره می‌شود؛ پس از خروج پرونده از صف مالی، پنجره فقط‌خواندنی است.

= 0.8.62.27 =
* FIX — «حتی یک فیش هم ثبت نمی‌شود» از ریشه: قفل add_option نسخه قبل اتمیک نبود (add_option وردپرس
  SELECT + INSERT…ON DUPLICATE KEY UPDATE است) و آپلودهای موازی همچنان هم را از صف مشترک پاک می‌کردند.
  صف حالا جدول اختصاصی bcrm_receipt_stage است: هر فیش یک ردیف (INSERT اتمیک، بدون قفل)، جدا برای هر
  کاربر و هر پرونده؛ ثبت نهایی فقط همان فیش‌های ثبت‌شده را از صف برمی‌دارد.
* NEW — فرمت آزاد (HEIC، PDF، Word/Excel، ZIP، فایل بی‌پسوند موبایل…) با خنثی‌سازی پسوندهای اجرایی؛ سقف
  هر فایل ۵۰ مگابایت؛ انتخاب چندفایلی (هر فایل یک ردیف)؛ آپلود فوری پس از انتخاب با وضعیت هر ردیف،
  تلاش خودکار در قطعی اینترنت، دکمه «تلاش دوباره»، کم‌حجم‌سازی خودکار عکس‌های حجیم، بازیابی فیش‌های
  آپلودشده پس از رفرش، و بررسی وجود همه فیش‌ها روی سرور پیش از ثبت نهایی.
* FIX — فرم نهایی دیگر خود فایل‌ها را دوباره نمی‌فرستد؛ پیاده‌سازی تکراری آپلود در frontend.js حذف شد؛
  لغو پنجره تأیید دیگر آپلود/ثبت را ادامه نمی‌دهد؛ ساخت thumbnail هنگام ثبت نهایی فیش‌ها خاموش است؛
  چک‌باکس‌های «ارسال فیش/وضعیت عودت» درست نمایش داده می‌شوند.

= 0.8.62.26 =
* FIX — ریشه واقعی «حتی یک فیش هم ثبت نمی‌شود» بسته شد (از گزارش پنل تشخیص روی سرور): صف آپلود
  فیش‌ها بین همه کاربران و همه پرونده‌ها مشترک است و هر آپلود یک درخواست جداگانه؛ بدون قفل،
  نوشتنِ موازی می‌توانست فیش‌های تازه‌ثبت‌شده را از صف پاک کند و ثبت نهایی کل دسته را با
  «شناسه ناشناخته» ناموفق کند. حالا کل خواندن/نوشتن صف زیر قفل فرایند است و ثبت نهایی همیشه
  صف را تازه زیر قفل می‌خواند.
* پنل تشخیص: رویدادهای قفل/شناسه ناشناخته با لیبل فارسی و فیلدهای شناسه/دلیل/تعداد در صف؛
  راهنمای صف مشترک با شماره پرونده؛ چیپ صف وقتی صف همین لحظه در حال نوشتن است «نامشخص» می‌شود.

= 0.8.62.25 =
* FIX — هات‌فیکس فتال صفحه پرونده روی برخی هاست‌ها: `disk_free_space` در لیست disable_functions
  برخی هاست‌های اشتراکی بسته است و در PHP 8 مثل تابع تعریف‌نشده فتال می‌داد؛ چیپ «فضای خالی دیسک»
  پنل تشخیص حالا با گارد `function_exists` رندر می‌شود و در صورت غیرفعال بودن، «نامشخص» نشان می‌دهد.
* بقیه پنل تشخیص (نشست، توکن، صف staging، سقف‌ها، رویدادهای آپلود) بدون تغییر.

= 0.8.62.24 =
* NEW — پنل تشخیص آپلود فیش روی صفحه پرونده (هر دو نمای رفع ناقصی و مالی): با بازکردن بخش
  «وضعیت آپلود فیش‌ها» دقیقاً مشخص می‌شود مشکل آپلود کجاست — نشست ورود، توکن آپلود، تعداد فیش‌های
  در صف staging، سقف‌های سرور (max_file_uploads/upload_max_filesize/post_max_size با یادآوری اینکه
  روی آپلود مرحله‌ای اثری ندارند)، فضای خالی دیسک و ۱۰ رویداد آخر آپلود از audit_log با لیبل فارسی
  (درخواست نرسیده، اعتبارسنجی رد شده، فایل به سرور نرسیده، فیش در صف قرار گرفت، ثبت نهایی و...).
* هیچ گارد یا محدودیت جدیدی به آپلود اضافه نشد — این نسخه فقط مشاهده‌پذیری (observability) است.

= 0.8.62.23 =
* NEW — پنل تایید مالی به سبک پلاگین فروش‌نت (دسکتاپ) با استایل خود CRM: صفحه پرونده حالا
  «فیش‌های این پرونده» را با کارت تک‌تک فیش‌ها نشان می‌دهد — مبلغ هر فیش (از یادداشت ردیف،
  ارقام فارسی هم پشتیبانی می‌شود)، لینک مشاهده فایل و نوع/زمان. بالای پنل سه کارت جمع:
  مبلغ اعلامی مشتری، جمع فیش‌ها و باقیمانده تا مبلغ اعلامی. فرم ثبت نهایی تایید مالی هم
  خلاصه همان سه عدد را بالای فرم دارد و فیلد «مبلغ تاییدشده بدون فاکتور» به‌طور پیش‌فرض
  با باقیمانده پر می‌شود (تایپ کاربر بازنویسی نمی‌شود). پرونده بدون فیش، پنل فیش را نشان نمی‌دهد.

= 0.8.62.22 =
* FIX — آپلود دسته‌های بزرگ فیش (۵۰/۱۰۰+) که «فقط تعداد محدودی ثبت می‌شد»: آپلود مرحله‌ای حالا
  ۴ فایل همزمان می‌فرستد (پنجره موازی) تا مجموع زمان در سقف timeout سرور/proxy نماند؛ مرحله
  نهایی‌سازی (Finalize) سقف زمان اجرای PHP را بالا می‌برد (set_time_limit + memory) و سقف گارد
  تعداد از ۲۰۰ به ۱۰۰۰ رسید. همچنان هیچ سقف تعدادی روی فیش‌ها نیست.

= 0.8.62.21 =
* NEW — همه تقویم‌های فرم‌های Workflow شمسی شدند: تاریخ پیگیری (ثبت تماس)، تاریخ فاکتور (تایید
  با فاکتور) و تاریخ پرداخت (تایید بدون فاکتور) حالا با تقویم جلالی داخل خود فرم انتخاب می‌شوند
  (همان تقویم «پیگیری بعدی» پرتال) — ورودی میلادی native حذف شد. تاریخ شمسی انتخابی سمت سرور به
  میلادی تبدیل و ذخیره می‌شود (Biawin_CRM_Normalizer::date — ورودی میلادی هم پذیرفته می‌شود تا
  فلوشبک نشکند). Timeline و جزئیات پرونده تاریخ‌های ذخیره‌شده را شمسی نمایش می‌دهند.

= 0.8.62.20 =
* NEW — نتیجه تماس جدید «در انتظار ارسال فیش» (awaiting_receipt) در مرحله اقدام «ثبت تماس» Workflow:
  انتخاب آن پرونده را در وضعیت «منتظر مشتری» (CUSTOMER_CONTACT_PENDING) نگه می‌دارد — مثل پیگیری
  مجدد — و در اکسل، ریز وضعیت «در انتظار ارسال فیش» ثبت می‌شود. فقط کارشناسان رفع ناقصی/مدیر.

= 0.8.62.19 =
* NEW — ستون «مرحله اقدام» در خروجی اکسل Workflow (هر دو شیت): همان گزینه‌های سلکت «مرحله اقدام»
  (workflow_step) — ثبت تماس، ثبت نتیجه بررسی، ارسال به تایید مالی، ثبت فاکتور برگشتی از مالی،
  اصلاح مبلغ اعلامی و تکمیل، تکمیل پروفایل، بایگانی (+ تخصیص/بارگذاری مدرک/ثبت نتیجه مالی/تکمیل
  خودکار). در شیت «پرونده‌ها» بین «وضعیت» و «ریز وضعیت» (مربوط به آخرین اقدام) و در شیت «اقدام‌ها»
  بعد از «رویداد» برای تک‌تک ردیف‌ها.

= 0.8.62.18 =
* NEW — ستون‌های جدید در شیت «پرونده‌ها» خروجی اکسل Workflow: ریز وضعیت (زیر وضعیت آخرین اقدام
  — نتیجه تماس/نتیجه بررسی/نتیجه مالی/نوع مدرک/وضعیت عودت)، تاریخ آخرین اقدام، ساعت آخرین اقدام
  و آخرین اقدام‌کننده (نام کاربری که آخرین رویداد را ثبت کرده). استخراج آخرین اقدام هر پرونده با
  MAX(id) GROUP BY case_id؛ پرونده‌های بدون اقدام این ستون‌ها را خالی دارند.

= 0.8.62.17 =
* NEW — خروجی اکسل کامل گزارش Workflow (دکمه «خروجی اکسل کامل» کنار Export CSV در گزارش‌های رفع ناقصی و مالی):
  یک فایل XLSX سه‌شیتی. شیت «پرونده‌ها» همان ستون‌های CSV؛ شیت «اقدام‌ها» تک‌تک رویدادهای Workflow
  (پروفایل ناقص، تخصیص، ثبت تماس، نتیجه بررسی، ارسال به مالی، تخصیص مالی، تأیید با/بدون فاکتور، عدم
  تأیید، برگشت از مالی، تکمیل، بایگانی، آپلود مدرک، فاکتور برگشتی) به‌همراه ریز وضعیت هر مرحله
  (نتیجه تماس، نتیجه بررسی، نتیجه مالی، نوع مدرک، وضعیت عودت) و داده‌های هر مرحله: مبلغ ادعایی
  مشتری، اختلاف تاییدشده، مبلغ اعلامی اصلاح‌شده، مبلغ تایید نهایی، مبلغ/شماره فاکتور برگشتی، شماره
  تماس ثبت‌شده، تعداد و جمع فیش‌ها و تاریخ پیگیری. شیت «خلاصه» شمارش هر (رویداد × زیر وضعیت) است.
  مبدأ داده‌ها metadata_json رویدادها در case_actions است؛ دسترسی همان گزارش‌ها، nonce همان export.

= 0.8.62.16 =
* NEW — تخصیص از فایل اکسل در صفحه «تخصیص شماره موبایل» (پرتال: تب تخصیص‌ها / ?bcrm_view=assignments):
  فایل XLSX/CSV شامل ستون CustomerID را آپلود کنید و یک کارشناس مقصد انتخاب کنید؛ پروفایل هر مشتری
  به همان کارشناس تخصیص می‌یابد. اگر پروفایل قبلاً تخصیص خورده باشد تخصیص فعالش به کارشناس جدید
  منتقل می‌شود (تکرار نمی‌شود)؛ تخصیص‌های فعال تکراری قدیمی هم اصلاح می‌شوند. تطبیق مشتری:
  source_customer_id → شناسه داخلی → customer_code. شناسه‌های تکراری یک‌بار پردازش و شناسه‌های
  ناشناخته با شماره سطر اکسل در «جزئیات آخرین تخصیص» گزارش می‌شوند. همان قفل‌گذاری/لاگ/Activity
  جریان دستی؛ nonce مستقل bcrm_assign_customers_excel؛ فقط مدیر (bcrm_manage_cases).

= 0.8.62.15 =
* NEW — تخصیص دسته‌ای از فایل اکسل در تب «تخصیص» (رفع ناقصی و مالی): فایل XLSX/CSV شامل ستون
  CustomerID را آپلود کنید و یک کارشناس مقصد انتخاب کنید؛ همه پرونده‌های بازِ هر مشتری
  (به‌جز تکمیل‌شده/بایگانی‌شده) به همان کارشناس تخصیص می‌یابد. شناسه‌های تکراری فقط یک‌بار
  پردازش می‌شوند؛ شناسه ناشناخته و مشتری بدون پرونده باز در پیام نتیجه با شماره سطر اکسل گزارش
  می‌شود. تطبیق مشتری: source_customer_id → شناسه داخلی → customer_code. سرپرست‌ها با همان
  دسترسی تخصیص (assign_deficiency_cases/assign_finance_cases)؛ nonce مستقل + لاگ ممیزی کامل.

= 0.8.62.14 =
* CRITICAL FIX — دانلود «خروجی اکسل» تب عودت‌ها در صفحات المنتور/شورت‌کد پرتال:
  خروجی حالا روی template_redirect (قبل از هر خروجی HTML) رهگیری می‌شود؛ وارنینگ
  «Cannot modify header information» و چاپ‌شدن باینری XLSX داخل صفحه برطرف شد.
  بافرهای خروجی قبلی قبل از استریم پاک می‌شوند (XLSX و CSV).

= 0.8.62.13 =
* NEW — فرمول مانده برای مشتریان چند-فاکتوری: عودت قطعی = جمع «آخرین رکورد هر کد فاکتور»
  (آپلود مجدد همان کد فاکتور = جایگزینی همان گروه، هرگز SUM تکراری). رکوردهای قدیمی بدون
  کد فاکتور (آپلودهای Customer-ID محور قبلی) یک گروه مشترک برای کل مشتری می‌سازند — با داده
  قدیم سازگار است و نیازی به پاک‌سازی برای درست کار کردن نیست.
* NEW — ابزار «حذف رکوردهای قبلی» در هر دو صفحه «ثبت عودت‌ها» و «ثبت استفاده‌شده‌ها»:
  حذف فقط رکوردهای منبع اکسل (اعلامی کارشناس هرگز حذف نمی‌شود)، فیلتر «فقط ردیف‌های بدون
  کد فاکتور»، فیلتر کد Batch، پاک‌سازی خودکار تگ قطعیِ یتیم، و ثبت کامل در لاگ ممیزی.

= 0.8.62.12 =
* NEW — تخصیص/برگشت تخصیص رفع ناقصی و مالی:
  «وضعیت تخصیص هر کارشناس» (جدول تعداد هر وضعیت برای هر کارشناس؛ اعداد کلیک‌پذیر به تب برگشت)،
  تب مستقل «برگشت تخصیص» با فیلتر کارشناس + انتخاب وضعیت‌های برگشت (چندتایی) + صفحه‌بندی واقعی
  (10/20/50/100/200 در صفحه) + چک‌باکس انتخاب‌همه؛ برگشت «همه کارشناسان» پشتیبانی می‌شود.
* NEW — همه جداول تخصیص/بررسی صفحه‌بندی واقعی دارند (COUNT + LIMIT/OFFSET به‌جای سقف ثابت 200)
  و چک‌باکس «انتخاب همه در این صفحه» در سرستون دارند.

= 0.8.62.11 =
* NEW — تطبیق ردیف اکسل عودت با «کد فاکتور» (یونیک) و در نبود آن «Customer ID»؛ اگر هر دو ستون باشند
  کد فاکتور ملاک است. هیچ‌کدام به‌تنهایی اجباری نیست — حداقل یکی باید باشد. تطبیق کد: دقیق روی نسخه
  نرمال‌شده (ارقام فارسی/عربی، ي/ك، صفر-ویدث) + fallback صفرهای ابتدایی و جست‌وجوی جزئی.
* NEW — نوع رکورد دوم «استفاده شده» (source_type='used') با صفحه آپلود جداگانه در منوی «ثبت استفاده‌شده‌ها»؛
  بدون هیچ تگی و فقط در فرمول مانده لحاظ می‌شود. آپلود مجدد همان کلید = جایگزینی (Last-Record، هرگز SUM).
* NEW — فرمول مانده در همه‌جا: مجموع فاکتورها − آخرین عودت قطعی اکسل − آخرین «استفاده شده»
  (کارت مانده پروفایل، ستون مانده تب عودت‌ها، KPI جمع مانده/منفی، فیلترهای منفی/مثبت/صفر، خروجی اکسل).
* NEW — ستون «استفاده شده» در جدول تب عودت‌ها با فیلتر دارد/ندارد (u_sign)، کارت KPI «جمع استفاده‌شده»،
  ستون جدید در خروجی اکسل، و نمایش نوع در تاریخچه عودت پروفایل.
* NEW — CustomerID پرونده حالا در هدر پروفایل نمایش داده می‌شود (کنار کد مشتری).
* DB — ستون invoice_code در refund_registers (نصب جدید + ارتقای امن با ensure_column؛ بدون تغییر داده).
* FIX(62.10) — حذف alias تعریف‌نشده rv/t از WHERE تب عودت‌ها با عبارت مرجع مشترک؛ فیلتر تگ با EXISTS
  (هر دو = AND)؛ مرتب‌سازی با alias واقعی؛ رگرسیون اجرایی SQLite اضافه شد.

= 0.8.62.9 =
* CRITICAL FIX — KPI «همه عودت‌ها» همه مشتریان CRM را می‌شمارد (28,876): جمعیت پایه تب حالا
  فقط مشتریانِ واقعاً دارای عودت است (تگ قطعی/اعلامی یا رکورد عودت قطعی اکسل) — بدون fan-out
  JOIN؛ هر مشتری دقیقاً یک سطر (COUNT(*) = COUNT(DISTINCT مشتری)).
* FIX — «هر دو تگ» حالا منطق AND واقعی است (هر دو تگ با هم)، نه OR.
* NEW — همه کارت‌های KPI حالا «تعداد + مبلغ + tooltip ؟» دارند؛ مبلغ هر کارت از جمعیتِ همان
  کارت (SUM با CASE داخل SQL) محاسبه می‌شود: قطعی = جمع آخرین عودت قطعی؛ مانده منفی = جمع
  مانده‌های منفی؛ اعلامی = 0 (مبلغ قطعی ندارد و اختراع نمی‌شود). فرمول مانده (مجموع فاکتورها −
  آخرین عودت قطعی اکسل) دست‌نخورده.
* NEW — دکمه «خروجی اکسل» (⤓): XLSX نتایج فیلترشده فعلی با همان WHERE/سورت لیست (سقف 50,000
  سطر؛ nonce-guarded؛ fallback CSV با UTF-8 BOM). کوئری واحد مرجع برای لیست/KPI/خروجی.
* REDESIGN — فیلتر ستونی اکسلی هر سرتیتر (popover: جست‌وجو، شامل/دقیقاً برابر، عملگرهای عددی،
  میان‌بر مانده منفی/مثبت/صفر، انتخاب تگ)، KPI کلیک‌پذیر با active state، سرور-ساید کامل،
  «پاک کردن همه فیلترها»، شمارنده «فیلتر فعال: N»، مرتب‌سازی سه‌حالته، ۴ کارت جمع — v0.8.62.8.

= 0.8.62.6 =
* CRITICAL FIX — Fatal «DivisionByZeroError» + Warning «Undefined array key r_pp» در تب عودتی‌ها:
  الگوی `?? 25` داخل in_array وقتی کلید در URL نبود با allowlist match می‌شد و بعد (int)$_GET
  مقدار null → 0 می‌داد؛ صفحه‌بندی تقسیم بر صفر می‌شد. حالا با isset امن شده است.
* NEW — دسترسی‌ها per-user در «کاربران و دسترسی‌ها»: دکمه «دسترسی‌ها» برای هر کاربر پرتال؛
  تیک‌زدن/برداشتن دسترسی‌های اضافی (مشاهده عودتی‌ها، ثبت عودت، عودت وجه، گزارش‌ها، ایمپورت،
  مدیریت پرونده‌ها) بدون تغییر دسترسی‌های نقش اصلی؛ تغییرات در لاگ ممیزی ثبت می‌شود.
* NEW — دسترسی سبک «مشاهده تب عودتی‌ها» (bcrm_view_refunded): فقط مشاهده/فیلتر، بدون دسترسی
  به صفحه آپلود اکسل؛ دارندگان آن به پروفایل مشتریان عودتی‌دار هم دسترسی دارند.
* NEW — نقش «سرپرست عودت وجه» حالا bcrm_refund_register هم دارد: تب «عودتی‌ها» و پروفایل
  مشتریان عودتی‌دار برای او از همین نسخه باز می‌شود (installهای موجود با مکانیزم upgrade خودکار می‌گیرند).
* NEW — تب «عودتی‌ها» پرتال با تجربه اکسلی: ۶ کارت KPI (کل، قطعی، اعلامی، هر دو تگ، جمع مانده، مانده منفی)،
  سرتیترهای قابل sort با فلش جهت، جست‌وجوی متنی (نام/CustomerID/موبایل) و صفحه‌بندی ۲۵/۵۰/۱۰۰ ردیفی.
* FIX — Warning «Undefined property: stdClass::$actor_name» در جدول «۱۰۰ ثبت اخیر» صفحه ثبت عودت‌ها: کوئری حالا display_name کاربر ثبت‌کننده را با LEFT JOIN جدول users می‌گیرد و رندر با ??='-': بدون warning حتی برای رکوردهای بدون کاربر.

= 0.8.62.3 =
* FIX دسترسی ادمین به «ثبت عودت‌ها»: نصب‌های موجود که administrator آن‌ها قبل از
  معرفی bcrm_refund_register ساخته شده بود، خطای «اجازه دسترسی ندارید» می‌گرفتند چون
  آپدیت ZIP فقط activation hook را اجرا نمی‌کند. حالا: (۱) administrator اتحاد همه
  دسترسی‌های CRM را به‌صورت additive می‌گیرد (هیچ دسترسی‌ای حذف نمی‌شود)، (۲) قلاب
  admin_init روی هر بارگذاری پنل، نقش‌ها/caps را دوباره همگام می‌کند، (۳) نشانگر
  biawin_crm_caps_marker ارتقای دسترسی را idempotent می‌کند — بدون deactivate/reinstall.
* مکانیزم ارتقای استاندارد: migration های ترتیبی idempotent (0.8.62.0، 0.8.62.2) با قفل
  bcrm_upgrade_lock، لاگ خطا بدون جلو بردن نسخه DB (retry امن)، و اجرای دیتابیس
  upgrade روی هر بارگذاری — نه فقط فعال‌سازی.
* ساختار ZIP استاندارد وردپرس: همه فایل‌ها داخل پوشه پایدار biawin-crm-integration/
  (بدون شماره نسخه) تا WordPress → Plugins → Add New → Upload Plugin آن را همان
  افزونه نصب‌شده تشخیص دهد و روی نسخه فعال، جریان Replace/Update استاندارد را ارائه کند
  — بدون نیاز به غیرفعال‌سازی دستی؛ افزونه فعال می‌ماند و داده‌ها دست‌نخورده می‌مانند.

= 0.8.62.2 =
* معماری آپلود اکسل عودت فقط-ادمین شد: صفحه «ثبت عودت‌ها» فقط در پیشخوان وردپرس
  (CRM بیاوین → ثبت عودت‌ها) با دسترسی اختصاصی bcrm_refund_register موجود است و
  از منوی/ناوبری پرتال کارشناسان حذف شد. تب «عودتی‌ها» پرتال و گزینه «عودت شده است»
  در نتیجه تماس کارشناس دست‌نخورده باقی‌مانده‌اند (دو Flow مستقل).
* Administrator دسترسی کامل دارد؛ کارشناس فقط در صورت تخصیص صریح cap می‌بیند —
  پیش‌فرض به agentهای عادی داده نمی‌شود.

= 0.8.62.1 =
* فیکس فیلتر عودت: sanitize_key مقادیر HTTP را lowercase می‌کرد و فیلترهای
  عودت قطعی/اعلامی/هر دو در تخصیص شماره و تب عودتی‌ها بی‌صدا به «همه» برمی‌گشتند؛
  حالا همه مقادیر (بزرگ/کوچک) درست resolve می‌شوند.
* عودت اعلامی کارشناس در تب «عودت‌ها» پروفایل با منبع «اعلام‌شده توسط کارشناس»،
  نام کارشناس و تاریخ/ساعت نمایش داده می‌شود (رکورد history بدون مبلغ؛ مانده تغییر نمی‌کند).
* دسترسی bcrm_refund_register به لیست نقش‌های «کاربران پرتال» اضافه شد.

= 0.8.62.0 =
* سامانه ثبت عودت‌ها مبتنی بر اکسل: صفحه جدید «ثبت عودت‌ها» با سرتیترهای customer id /
  وضعیت عودت (0-1) / مبلغ عودت. هر ردیف به تگ «عودت قطعی» + رکورد عودت تبدیل می‌شود؛
  ردیف‌های نامعتبر/مشتری پیدا نشده هیچ‌وقت بی‌صدا حذف نمی‌شوند و با شماره ردیف اکسل گزارش می‌شوند.
* کارت «مانده» در پروفایل مشتری = مجموع فاکتورها − آخرین مبلغ عودت قطعی (منفی = قرمز).
* تب «عودت‌ها» در پروفایل: تاریخچه کامل عودت‌های اعلامی/قطعی با مبلغ، منبع، ثبت‌کننده و Batch.
* نتیجه تماس جدید «عودت شده است»: تگ «عودت اعلامی» می‌زند و مشخص است توسط کارشناس اعلام شده.
* تب جدید پرتال «عودتی‌ها» با فیلتر اعلامی/قطعی/هر دو + دسترسی جدید bcrm_refund_register
  که در نقش جدید «ثبت‌کننده عودت اکسلی» و از بخش کاربران قابل تخصیص است.
* تخصیص شماره: فیلتر «وضعیت عودت» (قطعی/اعلامی/هر دو) + ستون وضعیت عودت در جدول تخصیص.

= 0.8.61.5 =
* The send_to_finance status guard no longer rejects silently: it now logs the refusal
  (case id + status) and tells the user the actual status in plain language — the 22-Sep
  log had 18 staged files with no finalize trace because the guard swallowed everything.
* Same transparency for the returned-invoice status guard.

= 0.8.61.4 =
* PER-ROW receipt persistence — the final release blocker: a repeated receipt (same file on
  several rows, the 17:33 production log) was previously "deduplicated" into ONE attachment
  and the extra rows' amounts silently dropped, leaving the case stuck. Every submitted row
  now materializes with its OWN amount; duplicate_count is reporting only.
* Staged finalization now imports through media_handle_sideload() (WP's native path for
  on-disk files) with a per-row temp copy — media_handle_upload() rejects non-PHP-uploaded
  files in real WordPress (is_uploaded_file), which would have failed every staged row.
* Inline path: repeated content rows keep their per-row amounts instead of being skipped.
* Harness: media_handle_upload shim now enforces is_uploaded_file; media_handle_sideload,
  wp_tempnam shims added; duplicate-semantics tests rewritten to per-row expectations.

= 0.8.61.2 =
* CRITICAL FIX: staging sideload fatal — wp_handle_sideload() takes its $file argument by
  reference; passing a literal array fatals under PHP 8 ("Argument #1 ($file) cannot be passed
  by reference"), which failed EVERY staged receipt upload with a 500 on 0.8.61.0/0.8.61.1.
  The variable is now assigned first. Test harness shim now matches the real by-reference
  WordPress signature so this class of bug can never hide again.

= 0.8.61.1 =

* Fixed the "all receipts fail to upload" incident on page-cached hosting: an expired nonce can no longer reject every staged upload — a valid logged-in session plus site token is accepted, and the browser auto-refreshes its upload config and retries.
* Failed uploads now show the exact server reason (per file) instead of a generic message, with automatic per-file retries for transient errors.
* Clearer errors for expired sessions (re-login guidance) and hosting-side blockers; extra diagnostics in the plugin log.

= 0.8.61.0 =

* The receipt upload limit is REMOVED, not just reported: every receipt file is now staged individually by the browser (one file per AJAX request) before final submit, so the host's per-request multi-file cap and post_max_size can no longer truncate the batch — 28 files (or more) fully persist.
* No row-count limit remains in the receipt form; the previous add-row cap and pre-submit count warnings are gone.
* Accepts every image format WordPress knows (JPG, PNG, GIF, WebP, AVIF, HEIC/HEIF, BMP, TIFF, ICO) plus PDF.
* Staged files live server-side under a per-form token with content-hash dedup and a 6h TTL; they are materialized into real attachments only at final submit and consumed on success.
* Final-submit validation: staged ids ↔ amounts 1:1, unknown/missing staged files fail loud with full rollback of the request's rows; previous-round attachments stay untouched.

= 0.8.60.1 =

* Pre-submit guard: the receipt form now warns BEFORE submission when more files are selected than the server accepts per request (max_file_uploads), and blocks the add-row button past the server cap.
* The truncation error for exceeded server caps now names the exact cause (host max_file_uploads cap) and includes step-by-step hosting-panel/.user.ini instructions instead of a generic incomplete-request message.
* A visible hint in the receipt form shows the server's current simultaneous-file cap.

= 0.8.60.0 =

* Fixed multi-file receipt upload data integrity: files are paired with their own row by ORIGINAL request index, so no file is dropped, renumbered, or attached to another row after deleting middle rows.
* Added a canonical `$_FILES` normalizer (original request keys, no positional fallback, explicit rejection of malformed entries).
* Duplicate receipt detection now uses SHA-256 of the actual file content; two different files with the same name are both accepted.
* A failed receipt row now rolls back all media/attachment rows created in the same request and the case is NOT moved to `FINANCE_REVIEW_PENDING` on partial failure.
* Detects server truncation (`max_file_uploads` / `post_max_size`) and returns a clear error instead of silently continuing with fewer files.
* Added safe diagnostic logging (counts and request indexes only) for every receipt upload request.

= 0.8.59.9 =

* Hardened deficiency/finance round-trip workflow for returned cases.
* Fixed scalar/malformed receipt upload detection so one uploaded receipt cannot be processed against multiple rows.
* Added per-request duplicate receipt suppression using uploaded file signatures.
* Allowed returned deficiency cases to be sent back to financial approval after follow-up work.
* Added an in-case returned-invoice action for `RETURNED_TO_DEFICIENCY` cases.
* Kept the upgrade code-only with no schema migration, Guard change, Run #7 continuation, or Run #8 implementation.

= 0.8.59.8 =

* Hardened deficiency finance handoff so selecting/uploading receipt files always keeps the case moving to financial approval after successful upload.
* Auto-detects receipt handoff from uploaded files or receipt amounts, even if the `ارسال فیش` checkbox is not posted by the browser.
* Processes uploaded receipt file indexes defensively for repeatable `case_documents[]` rows.
* Kept the upgrade code-only with no schema migration, customer mutation, invoice mutation, Guard change, Run #7 continuation, or Run #8 implementation.

= 0.8.59.7 =

* Renamed the deficiency workflow action from `ارسال به تایید مالی با فیش` to `ارسال به تایید مالی`.
* Added an `ارسال فیش` checkbox; receipt amount/file rows open only when this checkbox is selected.
* Allowed sending a case to finance without a receipt unless `ارسال فیش` is selected.
* Added `وضعیت عودت: عودت شده` tagging for finance handoff, with timeline, report KPI, CSV export, and REST export visibility.

= 0.8.59.6 =

* Fixed the receipt add/remove click handlers when the main frontend workflow initializer runs before `portal-fixes.js`.
* Deficiency Portal UI Polish: fixed repeatable receipt row alignment so the `+ افزودن فیش` action stays compact and predictable.
* Added global portal font selection in CRM settings.
* Added a per-user portal header font switcher with Persian-friendly font stacks.
* Applied the selected font across portal pages, embedded admin views, form controls, buttons, and workflow UI.

= 0.8.59.5 =

* Deficiency Portal UI Polish: fixed repeatable receipt row alignment so the `+ افزودن فیش` action stays compact and predictable.
* Added global portal font selection in CRM settings.
* Added a per-user portal header font switcher with Persian-friendly font stacks.
* Applied the selected font across portal pages, embedded admin views, form controls, buttons, and workflow UI.

= 0.8.59.4 =

* Deficiency Portal Workflow Fixes: added portal user deletion, fixed portal deficiency search URLs that used `s`, and tightened supervisor assignment return flow.
* Deficiency assignment return now requires selecting one agent and only returns selected assigned cases for that agent.
* Send-to-finance can attach multiple receipt files using `+ افزودن فیش`.
* Deficiency and finance KPI cards now use clearer status-specific counts and show `?` help tooltips.
* No customer data migration, deficiency case data migration, Guard change, Run #7 continuation, or Run #8 implementation.

= 0.8.59.3 =

* Deficiency Supervisor Assignment UI Hardening: supervisors can assign deficiency phone/customer cases to agents and return selected assigned cases from the same assignment page.
* Assignment tables now show the primary normalized mobile number so supervisors can select the correct number/customer row.
* The assignment page shows both ready-to-assign and assigned-for-return queues.
* Return assignment no longer requires selecting an agent.
* No customer data mutation, deficiency case data migration, Guard change, Run #7 continuation, or Run #8 implementation.

= 0.8.59.2 =

* Deficiency Role Capability Sync Hardening: repairs specialist role/cap definitions during code-only upgrades and before portal user creation.
* Newly created `bcrm_deficiency_agent` users now inherit `view_deficiency_cases`, `process_deficiency_case`, `send_case_to_finance`, and `bcrm_work_cases` consistently.
* Newly created `bcrm_deficiency_supervisor` users keep supervisor assignment/report capabilities consistently.
* No customer data mutation, deficiency case mutation, assignment mutation, Guard change, Run #7 continuation, or Run #8 implementation.

= 0.8.59.1 =

* Release B.2.3 Workload/Kartable Production Hardening: closed independent audit blockers in migrated workload and Kartable read paths.
* Filter-current Kartable workload counts and rows now flow through Reporting Consumer Adapter / Canonical Assignment SOT.
* Management aggregate workload APIs now require a proven reporting scope and fail closed for unsupported tampered department/organization scope.
* Card/list paths expose shared total_count semantics so pagination does not redefine totals.
* Trusted Guarded workload values render as trusted numbers; untrusted values render bounded unknown.
* No schema mutation, data migration, Guard change, Run #7 continuation, or Run #8 implementation.

= 0.8.59.0 =

* Release B.2.3 — Workload and Kartable SOT Migration: migrated assignment/workload dashboard and Kartable consumers to Reporting Consumer Adapter/SOT.
* Workload cards and lists use newest-active assignment semantics and avoid duplicate active row double counting.
* Guarded workload is separated from NORMAL_CANONICAL workable workload; unassigned uses NORMAL_CANONICAL without canonical active assignment.
* CSV/Excel exports, REST reporting, expert performance, detailed finance reports, and detailed case reports remain out of scope.
* No schema mutation, data migration, Guard change, Run #7 continuation, or Run #8 implementation.

= 0.8.58.0 =

* Release B.2.2 — Management Reports SOT Migration: migrated Management Reports summary consumers to Reporting Consumer Adapter/SOT.
* Report summary customer populations now use NORMAL_CANONICAL with Guarded, Blacklisted, and Unresolved visibility.
* Report summary financial values use ACTUAL_PAID_LIVE invoice evidence instead of customers.total_paid truth.
* CSV/Excel exports, REST reporting, expert performance, workload/Kartable, and detailed case reports remain out of scope.
* No schema mutation, data migration, Guard change, Run #7 continuation, or Run #8 implementation.

= 0.8.57.1 =

* Release B.2.1 Production Hardening: optimized the main dashboard actual-paid KPI with a database-side canonical aggregate path.
* Dashboard trust/failure notes now show bounded safe messages instead of raw adapter/database errors.
* Added code-only upgrade gates for direct 0.8.55.2, 0.8.56.0, 0.8.56.1, and 0.8.57.0 upgrades to 0.8.57.1.
* Removed inherited customer/reconstruction JSON artifacts from the Production package while preserving the official blacklist runtime dependency.
* Added render, performance, upgrade, package hygiene, and hardening regression coverage.

= 0.8.57.0 =

* Release B.2.1: migrated only the main management dashboard KPI cards to the Reporting Consumer Adapter.
* Dashboard customer population now separates `NORMAL_CANONICAL`, Guarded, Blacklisted live, and Unresolved.
* Dashboard financial KPI uses live actual paid from the Financial SOT instead of cached customer totals.
* Dashboard assignment and case KPI cards use the shared assignment/case adapter paths.
* CSV/Excel exports, REST APIs, agent dashboards, workload pages, case detail reports, Guard, Run #7, and Run #8 remain untouched.

= 0.8.56.1 =

* Release B.1 Hardening: fixed `NORMAL_PLUS_VISIBLE_QUARANTINE` public adapter output so IDs and counts preserve segment metadata.
* Documented all public adapter methods, including `reset_request_memo` and `public_methods`.
* Removed the inherited historical identity NDJSON data artifact from the Production release ZIP.
* No B.2 consumer migration, no schema change, no domain mutation, no Run #7 continuation, and no Run #8 implementation.

= 0.8.56.0 =

* Release B.1 Reporting Consumer Adapter Foundation.
* Added one shared read-only adapter/facade for future reporting consumers.
* Provides structured status/trust metadata, explicit scope checks, request-scoped memoization, export cell neutralization, population/financial/assignment/event/case adapter methods, and approved-paid unresolved behavior.
* B.1 DOES NOT make legacy dashboards correct.
* B.1 DOES NOT make legacy exports correct.
* B.1 DOES NOT implement Run #8.
* B.1 DOES NOT repair deficiency orphans.
* B.1 only provides the shared trusted infrastructure for B.2-B.7.

= 0.8.55.2 =

* Hardened Production validation query compatibility for MariaDB financial freshness and activity/audit double-count checks.
* Financial and event-risk query failures now return `QUERY_FAILED`, `trusted=false`, and `UNAVAILABLE` metrics instead of trusted zeros.
* Added separate Core Source-of-Truth health and Validation Execution health semantics.

= 0.8.55.1 =

* Hardened Reporting Source-of-Truth population handling so unavailable Guard or blacklist evidence blocks trusted `NORMAL_CANONICAL` reporting instead of being treated as zero exclusions.
* Added explicit Guard/blacklist source status, canonical trust metadata, divergence counters, and unassigned `NORMAL_CANONICAL` assignment reporting.
* Kept `0.8.54.2` to `0.8.55.1` production upgrade code-only with no schema/domain mutation.

= 0.8.55.0 =
* Reporting Source-of-Truth Foundation: population, financial, assignment, and validation services.
* New read-only Reporting SOT validation page.
* Guard/blacklist and Frozen Run #7 historical-only reporting semantics.
* No Run #8, no migration, no Guard mutation, no schema mutation.

= 0.8.40 =
* Read-only historical identity audit and default safety lock for bulk ownership/cleanup execution.

= 0.5.0 =
* Portal username/password authentication and user management.
* Atomic phone + invoice workflow.
* Invoice-phone relation table.
* Financial evidence and reconciliation workflow improvements.
* Service credit concurrency protections.
* Service/refund workflow enhancements.
* Audit and CSV reporting improvements.
* Multiple access-control and consistency fixes.

= 0.4.0 =
* Customer 360, financial reconciliation, complaints, service credit and service cases.

= 0.3.0 =
* MVP import, invoice aggregation and customer matching foundation.


== Changelog ==

= 0.8.62.10 =
* FIX — Shared Last Excel Refund SQL expression for balance signs, numeric search, refund/balance comparisons, KPI, table and export; removes undefined refund alias.
* Last Excel Refund remains the latest Excel row by id, not a sum. Positive/nonnegative filter retains >= 0.
* FIX — Refund/tag sorting uses actual SELECT aliases, also used by export.
* QA — Executable regression added; historical test harnesses are absent from the supplied ZIP.


= 0.8.40 =
* Read-only historical identity audit and default safety lock for bulk ownership/cleanup execution.
= 0.6.0 =
* Fast AJAX operator workflow: live reconciliation, no-refresh journey/services, unified phone+invoice attach, interaction persistence and service status updates.
