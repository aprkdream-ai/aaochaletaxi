# AaoChaleTaxi — Claude Instructions

Multi-tenant Taxi Booking & Fleet Management SaaS for India (INR, Asia/Kolkata, en + hi).
Domain: https://aaochaletaxi.com. Commercial product — no demo shortcuts, no dummy buttons.

## Session start (token rules)
1. Read this file, then `docs/PROGRESS.md`. Resume from "Next exact tasks".
2. Read other docs only when relevant: `docs/ARCHITECTURE.md`, `docs/DATABASE_SCHEMA.md`,
   `docs/DEPLOYMENT.md`, `docs/DECISIONS.md`, `docs/PROJECT_PLAN.md`.
3. Don't re-scan the repo or reprint files. Reuse existing services/traits/components.
4. Run focused tests per module (`php artisan test --filter=...`); full suite at phase end.
5. Update `docs/PROGRESS.md` + `docs/CHANGELOG.md` at the end of each phase/logical module.

## Stack
PHP 8.3, Laravel 12, Blade, Bootstrap 5, Alpine.js, Bootstrap Icons, Vite (build locally/CI only),
MySQL 8 (MariaDB 10.6+ ok). Database queue + database cache + database sessions.
Tests: PHPUnit, SQLite in-memory locally; MySQL 8 in CI.

## Hosting constraints (Hostinger shared hosting) — NEVER violate
- No Node, Redis, Supervisor, WebSockets, Docker, long-running workers in production.
- Queue: `database` driver, processed by scheduler (`queue:work --stop-when-empty`) via cron.
- Real-time: AJAX polling only. Assets compiled in CI into `public/build`.
- App code lives outside `public_html`; only the public entry + assets go into `public_html`.
- `public_html` of aaochaletaxi.com also hosts OTHER unrelated apps in subfolders — never delete
  or rsync --delete into `public_html` root.
- Optional integrations (Razorpay, WhatsApp, SMS, Maps, FCM) must degrade gracefully when unset.

## Architecture essentials
- Shared-database multi-tenancy. Tenant-owned models use `App\Models\Concerns\BelongsToTenant`
  (global scope + auto-fill `tenant_id` from `App\Support\Tenancy\TenantContext`).
- NEVER accept `tenant_id` from request input. It comes from the authenticated user or the
  verified `/{operator-slug}` route. Strip it from validated data.
- `users.tenant_id` NULL = platform-level user (super admin, platform staff, customer).
- `users.type`: super_admin | platform_staff | operator | driver | customer | vendor.
- Authorization: roles/permissions tables (tenant-scoped roles). Check with
  `$user->hasPermission('x')`, `can:` gates, `permission:` middleware, and Policies. Super admin
  passes via `Gate::before`. Never rely on hidden buttons alone.
- Panels / route prefixes: public `/`, super admin `/admin`, operator `/app`, driver `/driver`,
  customer `/account`, operator microsite `/o/{slug}`.

## Coding conventions
- Thin controllers → Form Requests (validation) → Services in `app/Services/*` (business logic).
- Transactions + `lockForUpdate()` for bookings, assignments, payments, subscriptions.
- Money stored as DECIMAL(12,2) in INR. Never float math for money in PHP — use bcmath helpers
  (`App\Support\Money`).
- Financial records are append-only: corrections via adjustment/reversal rows.
- Fare quotations store full breakdown JSON snapshot.
- Audit important actions via `App\Services\AuditLogger`.
- Named routes everywhere; Blade components in `resources/views/components`.
- Eager-load to avoid N+1. No raw SQL unless justified in DECISIONS.md.
- Don't add packages without recording reason in `docs/DECISIONS.md`.

## Security
CSRF, escaped Blade output (`{{ }}` only, never `{!! !!}` on user data), throttled auth,
upload validation (mimes + size, stored on non-public disk unless public by design),
webhook signature checks, secrets only in `.env`, no stack traces in production (`APP_DEBUG=false`).

## Deploy
CI/CD: `.github/workflows/ci-cd.yml` → tests (MySQL 8) → build → rsync over SSH →
`deploy/remote-deploy.sh` on server. Details in `docs/DEPLOYMENT.md`.
