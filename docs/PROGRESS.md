# Progress

**Last updated:** 2026-11-17 (UI v2)
**Current phase:** Phase 6 complete → next Phase 7 (Driver PWA)
**Production:** live at https://aaochaletaxi.com (release-based deploy, MySQL)
**Completed phases:** 0, 1 (foundation + UI v2), 2 (SaaS admin), 3 (operator fleet & team), 4 (booking engine), 5 (customer portal), 6 (finance), integrations core
**Tests:** 169 passing (SQLite + MariaDB locally; MySQL 8 in CI)

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

## Phase 4 — completed
- [x] Fare rules + calculator · bookings workflow · quotations (snapshots) · payments ledger · conflict-free assignment · OTP trips

## Demo data
- Local: `php artisan migrate:fresh --seed` (logins in DemoSeeder). Production: `php artisan demo:data install|remove --force`
  (labelled "(Demo)", no invoices). Current production demo logins: owner@shivtravels.test / Demo@2026, driver 9811122233 / Drive@2026.

## Phase 5 — completed
- [x] Operator booking page · OTP verification & OTP login · tracking page · online advance · reviews · customer account · notifications

## Deployment (while GitHub Actions is blocked by account billing)
- `SSH_KEY=~/.ssh/aaochaletaxi_deploy bash deploy/release.sh` (clean origin/main build → deploy.sh). Git identity in this repo:
  aprkdream-ai noreply address.

## Phase 6 — completed
- [x] GST invoices + credit notes + PDF · expenses · driver earnings & payouts · reports + CSV · admin settlements & reports

## Next exact tasks (Phase 7 — Driver PWA)
1. `/driver` mobile app shell: web manifest (driver scope), service worker (offline shell + cache-first assets), install prompt.
2. Trip list (today / upcoming / history) for the logged-in driver only; trip detail with pickup/drop, customer call,
   Google Maps navigation deep link, stops.
3. Start trip with customer OTP + start km; complete with end km, tolls/parking/waiting (reuse BookingService start/complete).
4. Availability toggle (on/off duty), earnings summary (reuse DriverEarningsService), documents expiry view.
5. Notifications: polling endpoint (`/driver/poll`, 30 s) for new assignments; FCM web push when configured (token register endpoint).
6. Optional location ping during trip (trip_locations) — store last position only (privacy), shown to operator.
