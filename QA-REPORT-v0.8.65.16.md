# QA Report — v0.8.65.16

## Scope
- Show `ثبت پیگیری` in every active Deficiency case, not only `RETURNED_TO_DEFICIENCY`.
- Keep access for deficiency supervisors and the assigned deficiency agent.
- Keep the five follow-up sub-statuses in one canonical place.
- Keep `ثبت فاکتور برگشتی از مالی` limited to returned-from-finance cases.
- Preserve direct send-to-finance for normal deficiency cases; returned cases still require the follow-up route for resend.

## Follow-up sub-statuses
1. توافق مبلغ تائید شده
2. ادعای واریزی بیشتر
3. عدم پاسخ
4. درانتظار ارسال فیش مجدد
5. ارسال به مالی و آپلود ادامه فیش ها

## Compatibility
The action accepts all active deficiency workflow states:
- INCOMPLETE_NEW
- INCOMPLETE_ASSIGNED
- DEFICIENCY_UNDER_REVIEW
- CUSTOMER_CONTACT_PENDING
- READY_FOR_FINAL_DECISION
- RETURNED_TO_DEFICIENCY

Duplicate `عدم پاسخ`, `در انتظار ارسال فیش`, and `ادعای واریزی بیشتر` choices are removed from the older contact/result locations whenever the user can use the canonical follow-up action.
