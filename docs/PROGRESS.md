# Progress

**Last updated:** 2026-11-17 (UI v2)
**Current phase:** Phase 3 complete → next Phase 4 (Booking engine & fare calculator)
**Production:** live at https://aaochaletaxi.com (release-based deploy, MySQL)
**Completed phases:** 0, 1 (foundation + UI v2), 2 (SaaS admin), 3 (operator fleet & team), integrations core
**Tests:** 133 passing (SQLite + MariaDB locally; MySQL 8 in CI)

## Phase 1 — completed
- [x] Laravel 12 + Bootstrap/Alpine/Vite setup, IST timezone, en/hi locale switch
- [x] Migrations: tenants, users, RBAC, plans/subscriptions, settings, audit, contact, notifications, framework tables
- [x] Tenancy core (`TenantContext`, `TenantScope`, `BelongsToTenant`) + middleware (`tenant`, `tenant.active`, `platform`)
- [x] Auth (email/phone login, throttle, operator & customer registration, password reset, blocked-user logout)
- [x] RBAC (catalog, default roles, `permission:` & `type:` middleware, Gate mapping)
- [x] Super admin dashboard + operator management (approve/reject/suspend/reactivate/KYC) with audit log
- [x] Operator dashboard + status page, customer account, driver home shell
- [x] Public website + SEO (sitemap, robots, JSON-LD, canonical), contact & fare-enquiry forms
- [x] Seeders: `PlatformSeeder` (idempotent, prod-safe), `DemoSeeder` (local only)
- [x] CI/CD workflow + release-based deploy scripts with smoke test and auto-rollback
- [x] UI v2: modern design system (slim Bootstrap, brand theme, light/dark), inline SVG icons, redesigned
      public site (hero quote form with trip tabs, fleet, routes, FAQ, pricing toggle), split-screen auth,
      sidebar panels, mobile nav drawer + sticky CTA, OG image, manifest, rich JSON-LD
- [x] Tests: 65 passing (unit, icons, public pages + SEO, auth, registration, tenant isolation, authorization, admin lifecycle)

## Pending (needs owner action)
- [x] Production DB on MySQL (2026-11-18)
- [ ] hPanel cron for `schedule:run` (no crontab over SSH; web-fallback scheduler active meanwhile)
- [ ] GitHub Actions secrets (SSH_HOST/PORT/USER/PRIVATE_KEY/KNOWN_HOSTS) so pushes to `main` auto-deploy
- [x] SUPPORT_PHONE set (+91 90094 34118)

## Phase 2 — completed
- [x] Enquiries inbox · plans CRUD · subscriptions (activate/renew/change/extend/cancel/payments/invoices)
- [x] Lifecycle (grace/expiry/reminders) + notifications (queued) · subscription guard middleware
- [x] KYC uploads & review · support tickets · platform settings (encrypted secrets) · audit viewer · staff & roles
- [x] Operator: business profile/logo, KYC, subscription & invoices, support; pending-tenant onboarding links

## Integrations — completed (pulled forward from Phase 9)
- [x] Razorpay online subscription checkout + webhook (idempotent), settings with test buttons for all channels
- [x] SMS (MSG91/Fast2SMS), WhatsApp Cloud API, SMTP/sendmail, Google Distance Matrix, FCM v1; message log

## Pending (dev, later phases)
- [ ] Use Maps distance + Places autocomplete in booking/fare flow (Phase 4/5)
- [ ] Driver PWA push subscription → FCM tokens (Phase 7); booking payments via Razorpay (Phase 5)
- [ ] PDF download of invoices (Phase 6, dompdf) — currently print-to-PDF page
- [ ] Plan limits for vehicles/drivers/branches enforced when those modules ship (Phase 3)

## Known issues
- None open.

## Demo credentials (local only, `php artisan migrate:fresh --seed`)
- Super admin: admin@aaochaletaxi.test / password
- Active operator: owner@shivtravels.test / password1 · Pending operator: owner@ganeshcabs.test / password1

## Phase 3 — completed
- [x] Branches, staff, custom roles · vehicle categories, vehicles, drivers (+ app login) · fleet documents
- [x] Maintenance & expenses · plan limits for all resources · daily expiry digest · real operator dashboard

## Next exact tasks (Phase 4 — Booking engine)
1. `fare_rules` (per tenant × category × booking type: base, per-km, min km, driver allowance/day, night window+charge,
   waiting/min, GST %, toll/parking/state-tax flags) + admin-free operator UI; seed sensible defaults per new tenant.
2. `FareCalculator` service (bcmath, breakdown array) incl. one-way vs round-trip min-km/day rules; uses
   `GoogleMapsService::distance()` when configured else manual km.
3. `bookings`, `booking_stops`, `booking_quotations` (snapshot JSON), `booking_status_logs`, booking numbers via `sequences`.
4. Workflow service: enquiry → quoted → confirmed → advance_paid → vehicle/driver assigned → started → completed →
   billed → settled; cancellation with reason; status log.
5. `trips` with assignment under `lockForUpdate()` + overlap check (vehicle & driver), idempotency key on create.
6. Operator UI: bookings list (filters, calendar-day view), create/edit wizard, quote send (WhatsApp/SMS link), assign modal.
7. Convert website enquiries → bookings (admin/operator).
