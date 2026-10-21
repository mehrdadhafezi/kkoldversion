# QA report v0.7.3

Static checks:
- PHP syntax check: required before packaging.
- JavaScript syntax check: required before packaging.
- Profile tab anchors remain present for overview/invoices/interaction/services/complaints/history.
- Collapsed sidebar has overflow-x hidden and no visible scrollbar CSS.
- Collapsed menu entries have data-tooltip/title/aria-label.

Runtime browser QA recommended:
1. Open a long customer profile, scroll deep, switch between every sticky tab.
2. Collapse sidebar and hover each icon; verify tooltip and no horizontal scrollbar.
3. Test a short-height laptop viewport and ensure nav can still vertically scroll without a visible browser scrollbar.
4. Expand sidebar, refresh, collapse again and verify persisted state.
5. Verify mobile drawer remains full-width and labels visible.
