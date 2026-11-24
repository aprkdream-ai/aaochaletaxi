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
| D19 | 2026-11-18 | Integrations via plain HTTP clients (Razorpay, MSG91, Fast2SMS, Meta Graph, Google, FCM v1) — no SDKs | Fewer dependencies on shared hosting; easy to fake in tests |
| D20 | 2026-11-18 | WhatsApp notifications use one approved utility template with `{{1}}` = message text | Business-initiated WhatsApp messages require templates |
| D21 | 2026-11-18 | Online payment activates "after current" when a paid period is running, else immediately | Customers never lose paid days |
| D22 | 2026-11-18 | One polymorphic `fleet_documents` table for vehicle + driver files | Same rules (private disk, expiry, audit); less duplicated code |
| D23 | 2026-11-18 | Expiry dates kept as columns on vehicles/drivers (mirrored from uploaded documents) | Fast filters, dashboard and reminders without joins |
| D24 | 2026-11-18 | Web-fallback scheduler (in-process, after response) when no cron heartbeat | Hostinger has no crontab over SSH; exec/popen disabled |
| D25 | 2026-11-18 | Booking status (enquiry→quoted→confirmed→assigned→started→completed/cancelled) separate from payment_status (unpaid/advance_paid/paid/refunded) | Advance & settlement happen in parallel with the trip; avoids combinatorial statuses |
| D26 | 2026-11-18 | Fare snapshot stores the rule's rates; final fare is recomputed from the snapshot, not the live rule | Rate edits never change confirmed bookings |
| D27 | 2026-11-18 | Trip time window (pickup → estimated end incl. return/buffer) used for overlap checks under row locks | Prevents double assignment without a scheduler service |
| D28 | 2026-11-18 | Tolls/parking/state tax are pass-through (no GST) | Reimbursements at actuals are outside taxable value |
| D29 | 2026-11-18 | Public fares use server-side Google distance only; without Maps the booking is an enquiry | Prevents fare manipulation from the browser |
| D30 | 2026-11-18 | Trip OTP stored encrypted (not hashed) | Customer must be able to view it on the tracking page |
| D31 | 2026-11-18 | Online advances are collected on the platform's Razorpay account and settled to operators (Phase 6) | Single gateway, no per-operator KYC with Razorpay |
| D32 | 2026-11-18 | Named rate limiters per action | Laravel's numeric `throttle:x,y` shares one counter per client across routes |
| D33 | 2026-11-18 | barryvdh/laravel-dompdf for PDFs | Pure PHP, works on shared hosting; DejaVu Sans renders ₹ |
| D34 | 2026-11-18 | Invoice number `PREFIX/YY-YY/NNNN` per operator & FY via `sequences` | GST rule 46: unique per FY, ≤16 chars, gap-free |
| D35 | 2026-11-18 | Corrections only via credit notes; invoices immutable (model guard) | GST compliance & audit trail |
| D36 | 2026-11-18 | Driver earnings computed on demand; payouts store a breakdown snapshot | No double source of truth; payouts auditable |
| D37 | 2026-11-18 | Advanced reports gated by plan flag | Growth/Business value proposition |
| D38 | 2026-11-18 | Driver app is a PWA served by Laravel (manifest + SW at `/driver/…`, scope `/driver/`) | No app-store build; works on shared hosting; installable on Android |
| D39 | 2026-11-18 | Driver alerts = database notifications + 30 s polling; FCM web push optional (Firebase JS from gstatic, loaded only when configured) | No WebSockets on shared hosting; push is a bonus |
| D40 | 2026-11-18 | Only the latest driver position is stored, only while a trip is started | Privacy (DPDP) and tiny storage; operators need "where is the car now", not tracks |
| D41 | 2026-11-20 | Self-host Plus Jakarta Sans (latin + latin-ext woff2 via Vite, preloaded) | Removes 2 third-party origins from the critical path; ₹ glyph lives in latin-ext; no Google requests (privacy) |
| D42 | 2026-11-20 | Speculation Rules prefetch (moderate) + hover `<link rel=prefetch>` fallback; side-effect GETs excluded (/driver, /b, /locale, pdf/file/export/invoice) | Near-instant page switches on shared hosting without a SPA |
| D43 | 2026-11-21 | Place suggestions via a server proxy (`/suggest/places`) to Places API (New) + a static India city list (`App\Support\India`) as fallback | Key stays server-side, results cached, works with no Google key, one UI for all sources; no extra JS SDK |
| D44 | 2026-11-21 | Free OpenStreetMap fallback: Photon (autocomplete/geocode) + OSRM demo server (distance), toggle `maps_free` | Zero cost for launch; both are fair-use without SLA, so heavy caching, short timeouts, self-disable on errors, manual km always possible; switch to Google (or self-hosted/paid OSM) when volume grows |
| D45 | 2026-11-23 | One product for taxi and general fleet: `tenants.business_type` (taxi / fleet / both) switches modules; core (vehicles, drivers, documents, maintenance, expenses, team, reports) always on | Same codebase and plans serve both markets; no forked panels |
| D46 | 2026-11-23 | Same plans for every business type; running both modules adds the plan's add-on price (`addon_price_monthly/yearly`), snapshotted on the subscription | Owner's choice: one price list, simple upsell; limits stay per plan |
| D47 | 2026-11-23 | Module gating through permissions: `App\Support\Modules` maps permission keys to modules and `User::permissionKeys()` drops keys of disabled modules | One enforcement point — routes (`permission:`), gates, policies and the sidebar follow automatically; role edits keep hidden-module grants so re-enabling restores them |
| D48 | 2026-11-23 | Business type is set at registration, afterwards changed only by super admin (audited); access switches immediately, add-on price applies from the next activation/renewal or a manual payment | Pricing depends on it; avoids self-service downgrades mid-period |
| D49 | 2026-11-23 | Fleet module v1 = passenger/corporate fleet only; goods/logistics (load capacity, LR/bilty, e-way bill) deferred | Owner's scope decision |
| D50 | 2026-11-23 | Fuel log stored on the fuel `vehicle_expenses` row (litres, odometer_km, full_tank), not a separate table | One money record per fill; no duplicated amounts; expense reports stay correct |
| D51 | 2026-11-23 | Mileage = full-tank-to-full-tank (partial fills add litres), bcmath, truncated to 2 dp; fill < 80 % of the vehicle average flagged once ≥ 3 data points | Standard, robust method; flags leaks/theft/wrong entries without false alarms on a new vehicle |
| D52 | 2026-11-23 | Service reminders from the latest maintenance record per vehicle+type (next date and/or km); digest: date stages 7/3/1/0/-1, km once at "≤ 500 km" and once at "reached" (cache keys) | A newer record of the same type clears the reminder automatically; km has no calendar so it is de-duplicated per stage |
| D53 | 2026-11-23 | Road tax is an optional expiry (checked only when set) | Many vehicles have lifetime road tax; a blank must not mark every vehicle "missing" |
| D54 | 2026-11-23 | Driver daily checklist: one per driver + vehicle + day (unique key + row lock), photo on private disk, problems need a note or photo and alert the owner; operator marks resolved | Accountability without blocking trips; simple, auditable |
| D55 | 2026-11-23 | Fleet trips reuse `trips` (booking_id nullable + fleet columns) instead of a new table | Overlap checks, driver app, odometer, location, earnings and reports work for both kinds with one code path |
| D56 | 2026-11-23 | Overlap rules extracted to `TripScheduler` (lock vehicle → driver → trip; exclude by trip id) | The old `booking_id != ?` filter silently ignored NULL-booking trips; one shared rule for bookings and fleet trips |
| D57 | 2026-11-23 | Fleet trips start without customer OTP (start/end km required); operator can start/complete for drivers without the app | Internal use has no customer; still records km for cost reports |
| D58 | 2026-11-23 | Fleet cost report on every plan (not gated by `advanced_reports`); department cost = trip km × vehicle ₹/km in the period (allocation, not ledger) | Core value of the Fleet module; transparent, reproducible allocation |
| D59 | 2026-11-23 | PlatformSeeder re-runs default tenant roles for every tenant on deploy (Owner gets new permissions) | New permissions (e.g. fleet_trips.*) reach existing businesses without manual steps |
| D60 | 2026-11-23 | Parts keep their own km: `km_run` accumulated on each removal + live (odometer − fitted_at_km) while fitted; movements append-only | Tyre/battery life follows the part across vehicles |
| D61 | 2026-11-23 | Incident payment and EMI payment each create exactly one `vehicle_expenses` row (EMI linked by loan_id + installment_no, unique) | One money ledger for reports; no double counting; DB-level duplicate guard |
| D62 | 2026-11-23 | EMI defaults to reducing-balance formula (bcmath); bank-given EMI can override | Matches Indian bank EMIs; operators often know the exact EMI |
| D63 | 2026-11-23 | Book value: WDV by full years then pro-rata, or SLM by days; management view only (not tax) | Simple, explainable numbers for the fleet report |
| D64 | 2026-11-23 | Attached-vehicle owner earnings = per km / per trip / monthly / % of trip revenue; suggested deduction = vehicle expenses in period, editable; payouts append-only (model guard) | Covers common Indian attachment deals; operator keeps control of the final amount |
| D65 | 2026-11-23 | Marketplace lists only operators that are active, opted in, Taxi module, booking page on, usable subscription and serving the pickup city (`tenant_service_cities.city_key`) | No leakage of fleet-only, suspended or unpaid businesses; city match is cheap and indexable |
| D66 | 2026-11-23 | Marketplace fares computed server-side with each operator's own fare rules (distance from Maps between cities); category list = platform default categories | Comparable, tamper-proof prices; operator custom categories are not comparable across operators |
| D67 | 2026-11-23 | Two paths: direct "Book" (session referral → microsite prefilled, source=marketplace) and quote request broadcast to top N (default 3); first accept wins under a row lock on the lead; customer phone hidden until accept | Simple for customers; fair race; protects customer data |
| D68 | 2026-11-23 | Commission % snapshotted on the booking, amount fixed at completion on the taxable fare (excl. GST & pass-through); settled in platform settlements (net may be negative = operator owes) | Matches how the platform collects online advances; append-only, auditable |
| D69 | 2026-11-23 | `vendor/bin/pint` applied repo-wide (CI runs `pint --test`) | Some files had drifted; CI would fail on style |
| D70 | 2026-11-24 | Hotwire Turbo Drive (`@hotwired/turbo` 8.0.23, JS only, bundled by Vite) on operator & admin panels (`<body data-turbo="true">`); public site, driver PWA, downloads, locale and logout stay full page loads | Forms/links without full reloads while keeping server-side validation, redirects and flash messages unchanged; no per-form JS; one switch to turn off |
| D71 | 2026-11-24 | Turbo snapshot cache disabled on panels (`turbo-cache-control: no-cache`), scroll kept on same-page redirects, assets tracked (`data-turbo-track`) | Alpine state never restored stale; saving a row keeps your place; new deploy reloads assets |
| D18 | 2026-11-17 | No fabricated reviews/stats on marketing pages | Honest launch; add real testimonials once collected |
