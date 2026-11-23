# Changelog

## 2026-11-24 — Phase 8C: fleet operations
- Fleet trips (Fleet module): assign a vehicle and driver for official use with purpose, department, requester, from/to and
  passengers; numbered FTyymm-NNNN; the driver gets an alert. Edit/reassign until started, cancel with reason.
- Driver app shows fleet trips; drivers start with the odometer (no customer OTP) and complete with closing km. Operators can
  start/complete for drivers without the app.
- Departments / cost centres. Dashboard "Fleet trips today" and a "New trip" button.
- Fleet cost report (all plans): km, hours, fuel and mileage, fuel/other/maintenance cost and cost per km per vehicle;
  department allocation; CSV export.
- Fix: vehicle/driver double-booking checks now also see trips without a booking (shared `TripScheduler`).
- Existing businesses get the new permissions automatically on deploy. 13 new tests (220 total, SQLite + MariaDB).

## 2026-11-23 — Phase 8B: core fleet upgrades
- Vehicles: type (car, SUV/MUV, van, tempo, bus, pickup, auto, two-wheeler), FASTag ID, road tax expiry (optional — blank =
  lifetime) with reminders and a "Road tax" document type.
- Fuel log: fuel expenses take litres (kWh for EVs), odometer and full-tank; the vehicle page shows mileage per fill, average
  km/l, average ₹/litre and flags fills far below the vehicle's average. Odometer updates from fuel entries.
- Service reminders: set the next service date and/or km on any maintenance entry; dashboard "Service due" card and the daily
  digest (7/3/1/0 days; 500 km before and when reached). Recording a newer entry of the same type clears it.
- Driver app "Check" tab: daily vehicle checklist (10 items, notes, camera photo). Problems alert the owner (email/SMS/WhatsApp
  when configured), show on the dashboard and the vehicle's "Daily checks" tab, and can be marked resolved.
- 10 new tests (207 total, SQLite + MariaDB).

## 2026-11-23 — Phase 8A: Taxi + Fleet business modules
- Operators choose what they run at registration: Taxi & travels, Fleet management, or Taxi + Fleet. Only the super admin can
  change it later (operator page → "Business type & modules", audited); operators see it on Subscription with a support link.
- Taxi-only features (bookings, fare rules, quotations, trip invoices, booking payments, booking page `/o/{slug}`) are switched
  off for fleet-only businesses: hidden from the sidebar/dashboard and blocked server-side (403/404). Core fleet features stay.
- Pricing: same plans for everyone; Taxi + Fleet adds the plan's add-on price (new fields in Admin → Plans, shown on the pricing
  page). Admin activation, online checkout and invoices include it; the subscription stores the business type and add-on part.
- Dashboard shows fleet KPIs (available vehicles, on trip, in maintenance, expenses) for fleet-only businesses.
- Custom roles: permissions of switched-off modules are hidden and kept, so switching the module back restores them.
- Admin operator list: business type shown and filterable. 12 new tests (197 total, SQLite + MariaDB).
- Server data: `php artisan db:seed --class=ModuleAddonPriceSeeder --force` (idempotent; never overwrites prices set in Admin).

## 2026-11-21 — Autosuggest (pickup/drop, state, city)
- Pickup/drop/stops (operator new booking, microsite, home quote) suggest as you type: operator's own recent addresses →
  Google Places Autocomplete (New, via the server key, cached 7 days, auto-off 1 h on auth errors) → built-in list of
  ~850 Indian cities/towns/airports. Free text always allowed; keyboard + screen-reader friendly (ARIA combobox).
- Picking pickup and drop on the operator form calculates the road distance automatically (when Maps is configured).
- State fields (operator registration, business profile, invoice buyer) are searchable dropdowns of the 36 states/UTs,
  validated server-side (`IndianState` rule) and normalised ("MP", "Orissa" → canonical). City fields suggest cities of
  the selected state and fill an empty state. Invoice form: GSTIN fills the state; inter-state ticks itself when the buyer
  state differs from the operator's.
- Endpoints `/suggest/places`, `/suggest/cities` (throttled 90/min/IP). Recent addresses only for operator/vendor users of
  that tenant — never on public pages. Unused "Browser key" Maps setting removed.
- Free maps without a Google key (default on, Admin → Settings → Google Maps → "Free maps"): place suggestions from
  OpenStreetMap Photon and road distance from OSRM (×1.4 time factor for Indian roads); cached, 4 s timeout, auto-off
  10 min on 429/5xx; Google takes priority when a key is set. Tests block all stray HTTP calls.

## 2026-11-20 — Performance
- Server profiled on production: panel pages render in 13–100 ms with 7–28 queries; opcache, config/route cache, Brotli
  and immutable asset caching already on — the slowness was client-side.
- Fonts self-hosted and preloaded (no fonts.googleapis/gstatic round-trips); instant navigation via Speculation Rules
  prefetch on hover/press (+ fallback), excluding pages with side effects.

## 2026-11-20 — Auth pages v3
- Operator registration is a 2-step wizard (Business → Owner login; opens on the step with server errors); customer
  sign-up uses a 2-column form; both fit a 720 px-high laptop screen without scrolling.
- Login/register: richer left panel (feature tiles, illustrative trip-status card, floating chips, grid + glow), dotted
  background, accent bar on the card, "Forgot password?" beside the Password label (`x-input` `aside` slot), sign-up
  choices as cards, autofill no longer paints inputs blue.

## 2026-11-18 — UI v3 (dashboards, confirm dialog, home visuals)
- Branded confirm dialog replaces the browser `confirm()` for every `form[data-confirm]` (danger/primary tone, action label,
  focus trap, Esc/backdrop to cancel). Driver "Complete trip" uses it too.
- Dashboards: dark hero banner with KPIs + quick actions (`x-hero-banner`); operator: upcoming pickups + 14-day bookings chart;
  admin: bookings this month + 30-day sign-ups chart (`x-bar-chart`, single series, hover/focus tooltips).
- Panel look: gradient sidebar with glowing active item, layered card shadows, gradient stat icons, soft page background.
- Home: animated "how a trip looks" route card in the hero, gradient service/fleet icons, connected steps.

## 2026-11-18 — Payment-gateway compliance pages
- New `/shipping-delivery-policy` (services only, ride & subscription delivery timelines) in footer + sitemap.
- Owner/legal name, registered address and GSTIN (Admin → Settings → Company) shown on contact, terms, refund, privacy,
  shipping pages and the footer; terms jurisdiction follows the registered address.

## 2026-11-18 — UI polish (panels + mobile)
- Admin/operator panels: denser type (15 px), slimmer inputs/buttons/cards, smaller stat cards on phones, full-width
  header actions on mobile.
- All panel tables stack into labelled cards on phones (labels auto-generated from `<thead>`; opt out with `.table-keep`).
- Public site: shorter mobile hero, wider mobile gutters, faster reveal; mobile sticky CTA hides while the quote form/footer is visible.
- Operator dashboard booking-page card: open + WhatsApp share (removed stale "coming next" text); booking payments table fits phones;
  reports date filter fits phones; fare preview shows paise consistently (₹92.50).
- Admin dashboard activity hides login noise.
- Tests: finance tests freeze time (night-charge window made them fail after 22:00).

## 2026-11-18 — Phase 7: Driver app (PWA)
- Installable driver app at `/driver` (manifest + service worker scoped to `/driver/`, cache-first build assets,
  network-only pages with offline fallback, install button, bottom tab navigation, safe-area aware).
- Trips: current / overdue / today / upcoming, history; trip detail with stops, call / WhatsApp customer and Google Maps
  navigation (pickup before start, drop + waypoints after); start with customer OTP + odometer (max 2 h early);
  complete with closing km, tolls, parking, state tax, waiting → final fare and amount to collect.
- On/off duty toggle (blocked during an active trip), earnings (month, last month, paid, pending, payouts), profile with
  licence/document expiry.
- New-trip alerts: database notification on assignment + 30 s polling banner (vibrate); FCM web push when Admin →
  Settings → Push has web config + VAPID key (token registered from the app).
- Live location during a started trip: only the latest point is stored (India bounds, 1/min); operator booking page
  links to it on Google Maps.
- Strict ownership: drivers only see their own trips (others → 404); driver users without a linked profile get a help page.
- Fix: Bootstrap `d-*` utilities override Alpine `x-show` — driver banners use `x-if`.

## 2026-11-18 — Phase 6: Finance
- GST invoices for completed bookings: per-operator FY numbering ≤16 chars (`SHIV/26-27/0001`), seller/buyer snapshots,
  SAC 996601, CGST+SGST or IGST, reimbursements outside taxable value, amount in words; immutable; credit notes
  (`SHIC/…`) + re-issue; HTML print + PDF (dompdf, DejaVu Sans); customer download from tracking page; WhatsApp share.
- Business expenses (monthly view by category), driver earnings (per-trip / commission / pro-rata salary + allowances)
  with append-only payouts and statements.
- Reports with date ranges & presets: summary (revenue, collections, costs, profit, outstanding, by method, daily chart),
  vehicle-wise & booking-wise profitability (Growth+ via `subscription_plans.advanced_reports`), GST register; CSV export
  (UTF-8 BOM, formula-injection safe).
- Admin: operator settlements of online advances (fee %, UTR, payment-level tracking) and 12-month platform reports.
- Fixes: date columns compared by date (Eloquent stores 'Y-m-d 00:00:00'); duplicate `operator.invoices.show` route
  (subscription invoices moved to `/app/subscription/invoices/{payment}`); report header layout.

## 2026-11-18 — Phase 5: Customer portal
- Operator booking page `/o/{slug}` (branding, verified badge, fleet, ratings, JSON-LD) with server-side fare estimate
  (Google distance only — client km ignored); no maps → booking saved as enquiry for a call-back quote.
- Mobile OTP (`OtpService`: SMS → WhatsApp → e-mail → log in non-prod; hashed, 10 min, 5 attempts, 3 sends/15 min);
  verified guests get a customer account automatically; passwordless "Mobile OTP" login.
- Customer tracking page `/b/{token}`: progress, trip OTP (encrypted at rest), driver & vehicle, fare, online advance
  (Razorpay, operator-configurable %), cancellation, 1–5★ review; reviews shown on the operator page.
- Customer account: all bookings across operators + profile; customer SMS/WhatsApp/e-mail updates (quoted, confirmed,
  assigned with OTP, completed, cancelled) — never blocks the workflow; operator alerts for online bookings/payments/cancellations.
- Operator profile: booking page on/off, customer notifications, online advance %, about text.
- Fix: plain `throttle:x,y` limits share one counter per IP/user across routes → replaced with named per-action limiters.

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
