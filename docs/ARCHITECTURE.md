# Architecture

## 1. Runtime
| Concern | Choice | Why |
|---|---|---|
| Language/framework | PHP 8.3, Laravel 12 | Server has PHP 8.3.x; Laravel 12 requires ≥ 8.2 |
| DB | MySQL 8 / MariaDB ≥ 10.6 | Hostinger provides MariaDB 11.x |
| Cache / session | `database` | No Redis on shared hosting |
| Queue | `database`, drained by scheduler every minute (`queue:work --stop-when-empty --max-time=50`) | No Supervisor |
| Scheduler | One hPanel cron: `* * * * * php artisan schedule:run` | Only cron is available |
| Real-time | AJAX polling (15–30 s) | No WebSockets |
| Assets | Vite build in CI → `public/build` | No Node in production |
| PDFs | (Phase 6) `barryvdh/laravel-dompdf` – pure PHP | Shared-hosting friendly |

Required PHP extensions (all present on server): bcmath, ctype, curl, dom, fileinfo, gd, intl,
json, mbstring, openssl, pdo_mysql, tokenizer, xml, zip.

## 2. Server layout (Hostinger)
```
~/domains/aaochaletaxi.com/
├── aaochaletaxi_app/        ← Laravel app (outside web root): app, vendor, storage, .env …
├── public_html/             ← web root (SHARED with other apps in subfolders!)
│   ├── index.php            ← custom front controller pointing to ../aaochaletaxi_app
│   ├── .htaccess            ← Laravel rewrite; existing dirs/files are served as-is
│   ├── build/  robots.txt  favicon.ico
│   ├── storage -> ../aaochaletaxi_app/storage/app/public  (symlink)
│   └── vahansetu/, mediaflow/, …  ← unrelated apps, never touched
└── deploy_backups/          ← one-time backup of the previous root index.php
```
The front controller calls `$app->usePublicPath(__DIR__)` so `public_path()`, Vite manifest and
asset URLs resolve to `public_html`.

## 3. Multi-tenancy (shared DB)
- `tenants` table = taxi operators. Every tenant-owned table has indexed `tenant_id` FK.
- `App\Support\Tenancy\TenantContext` (singleton) holds the current tenant.
- Resolution:
  - Authenticated operator staff/driver → `users.tenant_id` (middleware `tenant`).
  - Public operator microsite `/o/{tenant:slug}` → route binding, only `active` tenants.
  - Super admin → no tenant; may act on a tenant explicitly via admin routes (`withoutTenantScope`).
- `BelongsToTenant` trait: global scope `where tenant_id = current`; on `creating` sets
  `tenant_id` from context and throws if absent (prevents orphan/leaked rows).
- When no tenant is in context and user is not super admin, scoped queries return nothing
  (`TenantScope` applies `whereRaw('1=0')`) — fail closed.
- Future: custom domains / subdomains → add resolver in `ResolveTenant` middleware using
  `tenants.custom_domain`; no other code changes needed.

## 4. Users, roles, permissions
- `users.type`: super_admin, platform_staff, operator, driver, customer, vendor.
- `roles` (tenant_id NULL = platform role; else tenant role), `permissions` (global catalog,
  grouped), pivots `permission_role`, `role_user`.
- Default tenant roles seeded per tenant on registration: Owner, Branch Manager,
  Booking Manager, Accountant. Driver uses `type=driver`.
- `Gate::before` → super_admin allowed. `permission:<key>` middleware; `@can('<key>')` in Blade
  (gates are auto-resolved via `hasPermission`).

## 5. Application structure
```
app/
  Http/Controllers/{Admin,Operator,Auth,Public,Customer,Driver}
  Http/Middleware   (ResolveTenant, EnsureUserType, EnsurePermission, EnsureTenantActive)
  Http/Requests/...
  Models/ (+ Concerns/BelongsToTenant, Scopes/TenantScope)
  Services/ (TenantOnboardingService, AuditLogger, Booking/*, Fare/*, Billing/* …)
  Support/ (Tenancy/TenantContext, Money)
  Policies/
resources/views/{layouts,components,public,auth,admin,operator,customer,driver}
```

## 6. Booking concurrency (Phase 4)
Assignment inside `DB::transaction` with `lockForUpdate()` on vehicle/driver rows and an overlap
check on `trips` (`[start_at, end_at)` intervals). Unique `bookings(tenant_id, idempotency_key)`
prevents duplicate submission.

## 7. Financial model (Phase 6)
Append-only `payments`, `refunds`, adjustments. Invoices immutable once issued; corrections via
credit notes. Fare breakdown snapshot JSON stored on quotation & booking.
