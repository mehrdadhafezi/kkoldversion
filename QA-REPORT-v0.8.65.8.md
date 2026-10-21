# QA Report — v0.8.65.8

## Scope
رفع محدودیت تطبیق جمع فیش‌ها با مبلغ اعلامی هنگام ارسال پرونده از رفع ناقصی به تأیید مالی.

## Business rule
- حداقل یک فیش معتبر همچنان الزامی است.
- مبلغ هر فیش همچنان باید معتبر و بزرگ‌تر از صفر باشد.
- جمع فیش‌ها می‌تواند کمتر، مساوی یا بیشتر از مبلغ اعلامی مشتری باشد.
- اختلاف فقط Audit/Log می‌شود و ارسال به مالی را Block نمی‌کند.

## Paths covered
- Staged receipt upload.
- Direct/legacy receipt upload.

## Static verification
- PHP lint.
- ZIP integrity.
