# Progress

**Last updated:** 2026-11-17 (UI v2)
**Current phase:** Phase 1 complete → starting Phase 2 (SaaS Admin)
**Production:** live at https://aaochaletaxi.com (release-based deploy, SQLite fallback DB)
**Completed phases:** Phase 0 (discovery, docs, ERD, roadmap)

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

## Pending (dev)
- [ ] Admin: website enquiries inbox (contact_messages are stored; no UI yet)

## Known issues
- None open.

## Demo credentials (local only, `php artisan migrate:fresh --seed`)
- Super admin: admin@aaochaletaxi.test / password
- Active operator: owner@shivtravels.test / password1 · Pending operator: owner@ganeshcabs.test / password1

## Next exact tasks (Phase 2 — SaaS Admin)
1. Admin enquiries inbox (list/filter/mark handled) for `contact_messages`.
2. Subscription plans CRUD (`admin.plans.manage`), plan feature flags.
3. Subscription management: manual activation/extension/upgrade/downgrade, grace-period + expiry command in scheduler,
   `subscription_payments` table + invoices numbering.
4. Operator KYC document uploads (private disk) + admin review UI.
5. Platform settings UI (trial days, grace days, commission, integration keys encrypted) + audit log viewer.
6. Platform staff management (create staff, assign platform roles).
7. Support tickets (tables + operator/admin UI).
