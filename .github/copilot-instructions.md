# Copilot Instructions — AaoChaleTaxi

Laravel 12 / PHP 8.3 multi-tenant taxi SaaS for Indian shared hosting. Read `CLAUDE.md` for full rules.

- Tenant-owned models use the `BelongsToTenant` trait. Never take `tenant_id` from request input.
- Validation in Form Requests, business logic in `app/Services`, authorization via Policies,
  `permission:` middleware and `$user->hasPermission()`.
- Use DB transactions + `lockForUpdate()` for bookings, assignments and payments.
- Money: DECIMAL(12,2) INR, use `App\Support\Money` (bcmath). Financial rows are append-only.
- UI: Bootstrap 5 + Alpine.js + Bootstrap Icons, Blade components; no Tailwind, no SPA frameworks.
- No Redis/WebSockets/Node at runtime; database queue/cache/session; AJAX polling for live updates.
- Escape all output with `{{ }}`. Named routes only. Avoid N+1 (eager load).
- Docs: `docs/ARCHITECTURE.md`, `docs/DATABASE_SCHEMA.md`, `docs/PROGRESS.md`, `docs/DEPLOYMENT.md`.
