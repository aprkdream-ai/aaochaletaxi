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
| Assets | Vite build in CI → `public/build` (~32 KB CSS + 37 KB JS gzipped) | No Node in production |
| Icons | Inline SVG via `<x-icon>` (Bootstrap Icons copied to `resources/icons` by `npm run icons`) | No 130 KB icon font |
| Fonts | Plus Jakarta Sans (Google Fonts, variable, `display=swap`) | One request, system fallback |
| PDFs | (Phase 6) `barryvdh/laravel-dompdf` – pure PHP | Shared-hosting friendly |

Required PHP extensions (all present on server): bcmath, ctype, curl, dom, fileinfo, gd, intl,
json, mbstring, openssl, pdo_mysql, tokenizer, xml, zip.

## 2. Server layout (Hostinger)
```
~/domains/aaochaletaxi.com/
├── aaochaletaxi_app/
│   ├── releases/<utc>-<sha>/  ← immutable releases (last 5 kept)
│   ├── shared/{.env, storage/, database.sqlite*}
│   └── current -> releases/<id>   ← atomic symlink switched by deploy
├── public_html/             ← web root (SHARED with other apps in subfolders!)
│   ├── index.php            ← front controller → ../aaochaletaxi_app/current
│   ├── .htaccess            ← Laravel rewrite; existing dirs/files are served as-is
│   ├── build/ robots.txt favicon.svg site.webmanifest og-image.png icon-*.png
│   ├── storage -> ../aaochaletaxi_app/shared/storage/app/public  (symlink)
│   └── vahansetu/, mediaflow/, …  ← unrelated apps, never touched
└── deploy_backups/          ← backup of the previous root index.php (coming-soon page)

* SQLite only as temporary fallback until the hPanel MySQL DB is configured.
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

### Business modules (2026-11-23)
`tenants.business_type` = taxi | fleet | both (`App\Enums\BusinessType`) → modules `taxi`, `fleet`. Core is always on.
`App\Support\Modules` maps permission keys to modules (`bookings.*`, `fares.manage`, `customers.view`, `invoices.*`,
`payments.manage` → taxi); `User::permissionKeys()` removes keys of modules the tenant lacks, so `permission:` routes,
gates, policies and `config/navigation.php` follow. New module features MUST add their permissions to the map. Public
microsite `/o/{slug}` additionally requires the taxi module. Prices: `SubscriptionPlan::priceFor($cycle, $type)` is the
only place that computes plan price (+ add-on for both).
Trips: customer bookings and fleet trips share `trips`; `Trip::isFleet()` (booking_id NULL), `number()/pickupLabel()` for views.
All assignment overlap checks go through `App\Services\TripScheduler` — never query overlaps by booking_id.

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

## 8. Frontend / design system
- Theme tokens in `resources/scss/app.scss` (brand `#ffb703`, ink `#0b1220`), slim Bootstrap import
  (unused components/utilities removed), light/dark via `data-bs-theme` (set before paint in `<x-head>`).
- Layouts: `x-layouts.public` (sticky glass header, offcanvas mobile nav, sticky mobile CTA, footer),
  `x-layouts.auth` (split screen), `x-layouts.panel` (sidebar admin/operator), `x-layouts.driver` (driver PWA: bottom tabs, polling, SW).
- Components: `x-icon`, `x-input` (icon, password toggle, aria), `x-stat-card`, `x-empty-state`,
  `x-page-header`, `x-page-hero`, `x-section-head`, `x-status-badge`, `x-faq` (+FAQPage schema),
  `x-breadcrumbs` (+BreadcrumbList schema), `x-seo` (title/description/canonical/OG/Twitter), `x-json-ld`.
- JS: Bootstrap ESM modules (collapse, dropdown, modal, offcanvas, toast) + Alpine; scroll-reveal uses
  IntersectionObserver and is skipped for `prefers-reduced-motion`; content stays visible without JS.
- SEO: unique title/description per page, canonical, OG image (1200×630), Organization/WebSite/TaxiService/
  FAQPage/BreadcrumbList JSON-LD, sitemap.xml, robots.txt, web manifest. Panels/auth are `noindex`.

## 9. SaaS billing & onboarding (Phase 2)
- Subscription rows are never edited in place for plan/period changes: `SubscriptionService` creates a new row and
  sets `superseded_by` on the old one (renewal = starts at old `ends_at`; immediate = old row cancelled).
- `SubscriptionLifecycleService` (hourly): active/trialing → grace (`grace_days`) → expired; reminders at 7/3/1 days.
  Middleware `subscription` blocks dashboard routes without a usable subscription; profile, KYC, subscription and
  support stay reachable (also for pending/suspended tenants) so operators can always fix their account.
- Payments are append-only with gap-free invoice numbers per financial year and a frozen billing snapshot;
  invoice shows CGST+SGST when seller and buyer state match, else IGST (SAC 998314).
- KYC files on the private disk, streamed via authorised routes with `nosniff` + sandbox CSP. Approving all required
  document types auto-marks KYC verified.
- Middleware priority: `ResolveTenant` and `PlatformScope` run before `SubstituteBindings`, so route-model binding of
  tenant-owned models is already scoped (other tenants' IDs → 404).
