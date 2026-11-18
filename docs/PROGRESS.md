# Progress

**Last updated:** 2026-11-17 (UI v2)
**Current phase:** Phase 2 complete → next Phase 3 (Operator: branches, staff, vehicles, drivers)
**Production:** live at https://aaochaletaxi.com (release-based deploy, SQLite fallback DB)
**Completed phases:** 0 (discovery), 1 (foundation + UI v2), 2 (SaaS admin)
**Tests:** 114 passing (SQLite + MariaDB locally; MySQL 8 in CI)

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
- [ ] Production DB on MySQL (currently SQLite fallback until hPanel MySQL DB credentials are provided — DEPLOYMENT §4)
- [ ] hPanel cron for `schedule:run`
- [ ] GitHub Actions secrets (SSH_HOST/PORT/USER/PRIVATE_KEY/KNOWN_HOSTS) so pushes to `main` auto-deploy
- [ ] SUPPORT_PHONE in shared .env (enables Call button + phone in footer/contact)

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

## Next exact tasks (Phase 3 — Operator management)
1. Branches CRUD (plan `max_branches`), `users.branch_id` usage.
2. Staff management for operators (invite/create staff, assign tenant roles, deactivate) with `max_users`.
3. Custom role editor (tenant roles ↔ permission catalog), owner role protected.
4. Vehicle categories (platform defaults + tenant custom) and vehicles CRUD with documents & expiry dates (`max_vehicles`).
5. Drivers CRUD + driver documents + driver login user (`max_drivers`); availability flag.
6. Vehicle maintenance & expenses; expiry reminder command (insurance/permit/fitness/PUC/licence).
7. Extend `PlanLimitService` usage for vehicles/drivers/branches.
