# Biawin CRM v0.7.2

- Fixed customer search navigation: all customer searches now preserve `bcrm_view=customers` and no longer fall back to dashboard.
- Added server-side pagination to the customer list.
- Added selectable page sizes: 25, 50, 100, 200.
- Search term, filter and page size persist while paging/filtering.
- Added CustomerID to customer search and list identity line.
- Customer profile links now use the explicit portal URL rather than the current request URL.
