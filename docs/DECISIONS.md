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
| D12 | 2026-11-17 | Release-based deploy (`releases/` + `current` symlink) with pre-switch smoke test and auto-rollback | Zero-downtime, safe deploys without SSH-only tooling like Envoyer |
| D13 | 2026-11-17 | Business dates use DATETIME, not TIMESTAMP | Non-null TIMESTAMP fails on MySQL/MariaDB with `explicit_defaults_for_timestamp=OFF`; avoids 2038 limit |
| D14 | 2026-11-17 | `composer.json` `config.platform.php = 8.3.0` | Local PHP is 8.4; lock must stay installable on the 8.3 server |
| D15 | 2026-11-17 | Inline SVG icons (`x-icon`) instead of Bootstrap Icons font | Saves ~134 KB font download; only used icons ship |
| D16 | 2026-11-17 | Slim Bootstrap SCSS/JS imports, Plus Jakarta Sans | Lightweight pages (~70 KB gz CSS+JS) on Indian mobile networks |
| D17 | 2026-11-17 | Sessions/locale: Hindi via session switch on same URLs (no `/hi/` prefix yet) | Simple; revisit with hreflang URLs if Hindi SEO becomes a goal |
| D18 | 2026-11-17 | No fabricated reviews/stats on marketing pages | Honest launch; add real testimonials once collected |
