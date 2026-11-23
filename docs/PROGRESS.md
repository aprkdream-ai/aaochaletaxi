# Progress

**Last updated:** 2026-11-23 (Phase 8E)
**Current phase:** All roadmap phases complete; Phase 8 + 10 released to production 2026-11-23 (release 20261123045529-838022c)
**Production:** live at https://aaochaletaxi.com (release-based deploy, MySQL)
**Completed phases:** 0, 1 (foundation + UI v2), 2 (SaaS admin), 3 (operator fleet & team), 4 (booking engine), 5 (customer portal), 6 (finance), 7 (driver PWA), integrations core
**Tests:** 237 passing (SQLite + MariaDB locally; MySQL 8 in CI)

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
- [x] hPanel cron for `schedule:run` (verified heartbeat 2026-11-18)
- [ ] Firebase: service-account JSON + Web app config + VAPID key in Admin → Settings → Push (the Android google-services.json is not enough)
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
- [x] Driver PWA push subscription → FCM tokens (Phase 7); booking payments via Razorpay (Phase 5)
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

## Phase 7 — completed
- [x] Driver PWA (manifest, service worker, offline, install) · trips list/detail/history · OTP start & complete
- [x] Duty toggle · earnings · profile & document expiry · polling alerts + FCM web push · last-location sharing

Autosuggest done 2026-11-21: `x-place-input`, `x-state-select`, `x-city-input` (resources/js/suggest.js); reuse them for
Phase 8 search (from/to city) and service areas.

## Phase 8A — completed (2026-11-23): Taxi + Fleet business modules
- [x] `tenants.business_type` taxi/fleet/both; permissions mapped to modules (`App\Support\Modules`), filtered in `User::permissionKeys()`
- [x] Registration step-1 picker; super admin changes type (audited) on operator page; admin list filter
- [x] Same plans + add-on price for both (plan form, partner page, operator subscription, admin activation, Razorpay checkout); snapshot on subscription
- [x] Fleet-only: no bookings/fares/invoices/microsite; dashboard shows fleet KPIs; role editor hides & preserves taxi permissions
- Owner decisions: same plans + add-on; only super admin changes type; fleet v1 = passenger/corporate (no goods/logistics)
- Production action (after deploy): `php artisan db:seed --class=ModuleAddonPriceSeeder --force` — sets add-on ₹399/699/1299 per month
  (₹3990/6990/12990 per year) on starter/growth/business only where still ₹0; then adjust in Admin → Plans if needed

## Phase 8B — completed (2026-11-23): core fleet upgrades (all business types)
- [x] Vehicle type, FASTag ID, optional road tax expiry (+ `road_tax` document mirrors it); `Vehicle::scopeNeedsAttention`
- [x] Fuel log on fuel expenses (litres/kWh, odometer, full tank) → `FuelEconomyService` mileage per fill + average, low-mileage flag, ₹/litre
- [x] Service reminders (next date and/or km per maintenance type) on dashboard + daily digest (`FleetExpiryService::serviceItemsFor`)
- [x] Driver PWA "Check" tab: daily checklist (10 items, photo, notes) → `vehicle_inspections`; owner alert (`VehicleInspectionIssue`);
      vehicle "Daily checks" tab with resolve; dashboard "Daily check problems"; Hindi labels for the driver screen
- Server: nothing to seed — run migrations only (deploy does it).

## Phase 8C — completed (2026-11-23): fleet operations (Fleet module)
- [x] Departments / cost centres (`departments.manage`); fleet trips without booking (`fleet_trips.view|manage`, mapped to Fleet module)
- [x] `TripScheduler` shared overlap rules (fixed: NULL-booking trips were invisible to booking assignment); `FleetTripService`
      create/edit/start/complete/cancel with row locks; `FTyymm-NNNN` numbers; driver notified (generalised `DriverTripAssigned`)
- [x] Driver PWA: fleet trips in list/history, start with km (no OTP), complete with km; earnings null-safe
- [x] Operator: trips list/filters, form with live busy hints, detail with start/complete/cancel; dashboard "Fleet trips today"
- [x] Fleet cost report (all plans): per vehicle km/hours/fuel/km-l/costs/₹ per km; per department allocated cost; CSV
- Server: migrations only; PlatformSeeder (runs on deploy) now gives existing Owner roles the new permissions.

## Phase 8D — completed (2026-11-23): fleet extras (all business types)
- [x] Tyres & parts: stock, fit/remove/scrap with odometer, km run across vehicles, warranty reminders (`FleetPartService`)
- [x] Incidents & challans with photo, insurance claim status, one-time payment → vehicle expense (`VehicleIncidentService`)
- [x] Loans: reducing-balance EMI, schedule, mark EMI paid → `emi` expense (unique per installment), auto-close, EMI-due reminders
- [x] Purchase cost + WDV/SLM depreciation → book value on vehicle page and fleet report (`App\Support\Depreciation`)
- [x] Attached/vendor owner terms on vehicle; owner statements & append-only payouts (`VehicleOwnerService`)
- Server: migrations only.

## Phase 8E — completed (2026-11-23): Marketplace (Taxi module)
- [x] Operator opt-in + service cities (profile); listing rules (active, taxi module, booking page, subscription)
- [x] Public `/cabs` compare (server-side fares per operator, ranking verified → rating → trips), `/cabs/{city}` SEO pages, sitemap, nav link
- [x] Direct "Book" → session referral → operator page prefilled; booking source=marketplace with commission %
- [x] Quote request (OTP-verified) broadcast to top N; operator inbox (badge) accept/decline; first accept wins (lead row lock); expiry every 5 min
- [x] Commission fixed at completion; platform settlements deduct commission (negative net = operator owes)
- [x] Repo-wide pint formatting (CI style check)
- Server: migrations only. Optional: Admin → Settings → Fees: marketplace commission %, operators per request, minutes to accept.

## Release rule
Owner wants ONE deploy after all phases are complete (see memory). Commits stay local until Phase 11.

## Phase 10 — completed (2026-11-23): QA & security
- [x] Route audit: every new operator route behind `permission:` (pre-existing support/status/documents check in controller)
- [x] Tenant isolation tests for all new tables (trips, departments, inspections, parts, incidents, loans, payouts, offers)
- [x] Marketplace: no Maps call for cities nobody serves (quota abuse); phone hidden until accept; throttles on all public endpoints
- [x] Query-count check (3 vs 12 rows) on 12 heavy pages — all flat after batching owner statements (`computeMany`)
- [x] `composer audit` / `npm audit`: no advisories; pint clean; suite green on SQLite + MariaDB
- [x] DEPLOYMENT.md §8: consolidated release steps + launch checklist

## Phase 11 — released 2026-11-23
- [x] DB backup `deploy_backups/db-before-phase8-20261123-045458.sql.gz`; pushed main; `deploy/release.sh`; 5 migrations OK; smoke + health OK
- [ ] Owner to run on server: `php artisan db:seed --class=ModuleAddonPriceSeeder --force`, then review Admin → Plans / Settings → Fees
- [ ] Ask operators to opt in to the marketplace (Business profile → Marketplace + cities) before promoting `/cabs`

Original release steps:
1. Push `main`, run `deploy/release.sh` (migrations run automatically; PlatformSeeder refreshes Owner permissions).
2. Server: `php artisan db:seed --class=ModuleAddonPriceSeeder --force`; review Admin → Plans add-on prices and Admin → Settings → Fees (marketplace).
3. Smoke test: /admin, /app (taxi + fleet tenant), /driver, /cabs, /o/{slug}.
