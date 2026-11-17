# Progress

**Last updated:** 2026-11-17
**Current phase:** Phase 1 — Foundation (core done; see pending)
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
- [x] Tests: 58 passing (unit, public pages, auth, registration, tenant isolation, authorization, admin lifecycle)

## Pending (carry into Phase 2)
- [ ] Production DB on MySQL (currently SQLite fallback on server until hPanel MySQL DB is created — see DEPLOYMENT §4)
- [ ] hPanel cron for `schedule:run`
- [ ] GitHub secrets for auto-deploy (if not yet set)
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
