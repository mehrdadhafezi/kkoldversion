# v0.8.2 — Safe identity linking hardening

- Phone search now returns only invoices that directly contain the searched phone in `invoice_phones`; it no longer expands to every invoice owned by the profile currently holding that phone.
- Linking a phone+invoice attaches only the explicitly searched/selected phone (or a single invoice phone fallback for invoice-code searches), never every phone stored on the invoice.
- Shared/high-risk phone numbers appearing on 10+ invoices are blocked for ordinary agents and require supervisor review.
- Collapsing an empty source profile no longer cascades all remaining source phones into the target. Remaining phones are preserved as unassigned evidence.
- Link audit metadata now records invoice, explicit phone(s), invoice-phone snapshot, source profile, target profile and actor.
- The latest interaction entered by the current agent is explicitly editable; the primary button shows “ذخیره تغییرات” when updating the same interaction.
