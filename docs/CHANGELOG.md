# Changelog

## 2026-11-18 — Phase 4: Booking engine
- Fare rules per operator × vehicle category × trip type (auto-seeded suggested rates; editable; GST 0/5/12/18).
- `FareCalculator` (bcmath): min km (per day for round trips), driver allowance, rental packages with extra km/hours,
  night window, waiting, discount, GST on fare only, tolls/parking/state tax pass-through, rupee round-off.
- Bookings: idempotent create, numbering `BK<yymm>-<n>` per operator, stops, quotation versions with full snapshot
  (rates frozen), confirm, append-only payments ledger (advance/balance/refund), cancellation.
- Assignment with row locks (vehicle → driver) + time-window overlap checks; compliance/availability checks;
  4-digit customer OTP (hashed) sent by SMS when configured; start with OTP; complete with actual km → final fare.
- Operator UI: bookings list (tabs/search/date), new-booking form with live fare preview and Google distance,
  booking detail with workflow actions, WhatsApp quote share, payments, timeline; fare rule editor.
- Demo data now includes quoted, assigned and completed bookings; `demo:data` install/remove command.
- Fixes: Alpine `x-show` vs Bootstrap `!important` display utilities; initial fare preview type.

## 2026-11-18 — Phase 3: Operator management (fleet & team)
- Branches (head office, plan `max_branches`), operator staff (roles, branch, status; last Owner protected; `max_users`),
  custom role editor (tenant permissions only; Owner locked).
- Vehicle categories (7 platform defaults + operator custom), vehicles with Indian registration validation/normalisation,
  insurance/permit/fitness/PUC expiry, photo, availability, soft delete; documents (private), maintenance, expenses.
- Drivers with licence validation, Aadhaar last-4 only, pay type, photo, documents, driver app login (mobile + password).
- `PlanLimitService` enforces vehicles/drivers/branches/users; friendly "Upgrade plan" banner.
- `fleet:expiry-reminders` daily digest (30/15/7/3/1/0/−1 days) via mail/SMS/WhatsApp; dashboard "documents needing attention".
- Operator dashboard with real fleet stats, setup checklist and plan usage; admin operator page shows fleet counts.
- Production switched to MySQL; web-fallback scheduler until hPanel cron exists; E.164 support phone links.
- UI fixes found via logged-in screenshots: missing `.min-w-0` utility (mobile overflow), Bootstrap progress not imported,
  expiry badge label bug, tab colours, role shown in sidebar.

## 2026-11-18 — Integrations (Phase 9 core, pulled forward)
- Super admin settings split into Payments (Razorpay), Email (SMTP/sendmail), SMS (MSG91 / Fast2SMS), WhatsApp Cloud API,
  Google Maps, Push (Firebase FCM v1) — encrypted secrets, "required secret" validation, live "Send test" per channel.
- Razorpay online subscription checkout (order → Checkout.js → HMAC-verified callback) + signed webhook
  (`/webhooks/razorpay`, CSRF-exempt); idempotent fulfilment with amount check; renews after current paid period.
- Notifications fan out to mail + SMS + WhatsApp when configured; runtime mail config from settings.
- `message_logs` delivery log (sent/failed/skipped + provider id/error) with admin viewer; `payment_orders` table.
- Distance Matrix service (cached) and FCM service (JWT signed with openssl, no SDK).
- UI: readable activity feed, fixed invisible active item in settings menu; richer DemoSeeder (paid plan, invoice, ticket, enquiries).

## 2026-11-18 — Phase 2: SaaS admin
- Subscriptions: activate/renew (now or after current), change plan (limit-checked), extend, cancel, record payment;
  GST tax invoices (`ACT/<FY>/<n>`, CGST/SGST vs IGST), printable; operator billing page with usage & history.
- Lifecycle command `subscriptions:process` (hourly): grace → expired, 7/3/1-day reminders; queued mail + DB notifications.
- KYC: private uploads (PDF/JPG/PNG/WEBP ≤5 MB), operator manage, admin approve/reject, auto-verify.
- Support tickets (operator ↔ staff, internal notes, assignment, status), website enquiries inbox.
- Plans CRUD, platform settings (billing, company/invoice, encrypted integration secrets), audit log viewer,
  platform staff & roles, operator business profile with logo/brand colour, approval email.
- Fixes: route-model binding ran before tenant context (now prioritised); `User::roles()` eager-load returned no
  roles for tenant users (filter moved to permission check); date-dependent trial tests.

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
