# Technical Decisions

| # | Date | Decision | Reason |
|---|---|---|---|
| D1 | 2026-11-17 | Laravel 12 on PHP 8.3 | Server runs PHP 8.3.x; Laravel 12 supports 8.2–8.4 |
| D2 | 2026-11-17 | Shared-DB multitenancy with `tenant_id` + global scope (no package) | Single DB on shared hosting; simple, testable; no stancl/tenancy overhead |
| D3 | 2026-11-17 | Custom roles/permissions tables (no spatie) | Need tenant-scoped roles; small, explicit code; fewer deps |
| D4 | 2026-11-17 | Hand-built Bootstrap 5 auth views (no Breeze/Jetstream) | Breeze is Tailwind; spec mandates Bootstrap |
| D5 | 2026-11-17 | Database queue drained by scheduler | No Supervisor on shared hosting |
| D6 | 2026-11-17 | App outside `public_html`, custom front controller with `usePublicPath()` | `public_html` hosts other apps; keeps .env/vendor non-public |
| D7 | 2026-11-17 | Never `rsync --delete` into `public_html` root; only into `public_html/build` | Protect unrelated sibling apps |
| D8 | 2026-11-17 | Operator microsites at `/o/{slug}` (not `/{slug}`) | Avoid collisions with sibling app folders and public pages; ready for subdomain/custom-domain later |
| D9 | 2026-11-17 | CI tests on MySQL 8 service, local tests on SQLite memory | Catch MySQL-specific issues before deploy; fast local loop |
| D10 | 2026-11-17 | Operator registration creates tenant in `pending` status; Super Admin approves | KYC/approval workflow required |
| D11 | 2026-11-17 | New tenants get 14-day trial on STARTER plan (configurable via platform setting) | Sensible default; reversible |
