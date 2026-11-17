# Changelog

## 2026-11-17 — UI v2 (design system + SEO + performance)
- New theme: taxi-yellow/ink palette, Plus Jakarta Sans, glass header, gradient hero with grid, light/dark.
- Home: trip-type tabs quote form (prefill via `?trip=`, `?pickup=`, `?drop=`), trust strip, services, fleet,
  steps, bento features, popular routes, operator dashboard preview, FAQ, CTA band; mobile sticky CTA + drawer nav.
- Pricing page: monthly/yearly toggle (DB-driven), comparison table, operator FAQ. New services/about/contact/legal layouts.
- Auth split-screen with password visibility toggle; panel sidebar redesign, avatars, soft badges, modern tables.
- Weight: icon font removed (inline SVG), slim Bootstrap SCSS + ESM JS → ~32 KB CSS + 37 KB JS gzipped (was ~120 KB incl. font).
- SEO: OG/Twitter image, web manifest, apple-touch icon, Organization/WebSite/TaxiService/FAQPage/BreadcrumbList schema,
  unique meta descriptions, branded error pages. Hindi strings expanded.
- Fix: `Money::format()` supports whole-rupee display.

## 2026-11-17 — Phase 0 + Phase 1 foundation
- Laravel 12 skeleton; Bootstrap 5 + Alpine + Bootstrap Icons via Vite (Tailwind removed).
- Shared-DB multi-tenancy: `TenantContext`, `TenantScope` (fail-closed), `BelongsToTenant` (forces tenant_id, blocks changes).
- Tables: tenants, users (type/status/phone/tenant), roles, permissions, pivots, subscription_plans, subscriptions,
  tenant_settings, platform_settings (encrypted secrets), audit_logs, contact_messages, notifications.
- Auth: login by email or mobile, throttling, operator registration (tenant + owner + roles + 14-day trial),
  customer registration, password reset, inactive-user logout.
- RBAC: permission catalog, default tenant & platform roles, `permission:`/`type:` middleware, `Gate::before`.
- Super admin: dashboard, operator list/search/filter, detail, approve/reject/suspend/reactivate, KYC status; audited.
- Operator dashboard + account-status page; customer account; driver home shell.
- Public site: home (fare enquiry form), about, services, partner (plans), contact, privacy, terms, refund,
  sitemap.xml, robots.txt, JSON-LD, canonical/OG tags, Hindi locale switch.
- Security headers middleware; IST timezone; Indian money formatting helper.
- CI/CD: GitHub Actions (Pint, MySQL 8 migrations up/down, tests) → release-based SSH deploy with smoke test + auto-rollback.
- Fix: subscription dates use DATETIME (non-null TIMESTAMP fails on MySQL with explicit_defaults_for_timestamp=OFF).
