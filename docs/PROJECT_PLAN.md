# Project Plan & Roadmap

Status legend: ✅ done · 🟡 in progress · ⬜ not started

| Phase | Scope | Acceptance criteria | Status |
|---|---|---|---|
| 0 Discovery | Repo/server inspection, docs, ERD, roadmap | All docs present; hosting constraints recorded | ✅ |
| 1 Foundation | Laravel 12 setup, auth (email/phone login, register operator, reset), roles & permissions, tenants, isolation, base UI, public site shell, CI/CD | Tests for auth, tenant isolation, permissions pass; deployed to production | ✅ (MySQL switch pending on server) |
| 2 SaaS Admin | Admin dashboard, operator approval, KYC review, plans CRUD, subscriptions (trial/activate/extend/suspend), tenant settings, platform settings, audit log viewer, support tickets | Super admin can onboard, approve and bill a tenant end-to-end | ✅ (online payment in P9) |
| 3 Operator | Operator dashboard, branches, staff + role management, vehicles (+docs, maintenance, expenses), drivers (+docs), plan limits enforcement | Plan limits enforced; all CRUD tenant-isolated (tests) | ✅ |
| 4 Booking engine | Fare rules + calculator, booking types, enquiry→settlement workflow, quotations with snapshot, availability, assignment with locking, trips, status logs | Concurrent assignment test can't double-book; fare snapshot immutable | ✅ |
| 5 Customer portal | Public search & booking, guest booking with OTP-ready verification, customer account, history, cancellation, ratings, operator microsite `/o/{slug}` | Customer can book & pay (mock gateway) | ⬜ |
| 6 Finance | Invoices (GST-ready), receipts, payments, refunds, expenses, driver/vendor settlements, reports, PDF + CSV export | Append-only ledger tests; exports tenant-scoped | ⬜ |
| 7 Driver PWA | Manifest + service worker, trips list, start (OTP)/complete, earnings, polling notifications | Installable PWA; works on mobile | ⬜ |
| 8 Marketplace | Listings, fare comparison, booking requests, operator acceptance, commission tracking, reviews | Commission separate from direct bookings | ⬜ |
| 9 Integrations | Razorpay (orders, verify, webhook), WhatsApp Cloud, SMS, mail, Maps, FCM — all optional with mock mode | Works with integrations disabled | ⬜ |
| 10 QA & security | Feature/isolation/conflict/payment tests, security review, perf (indexes, caching) | Full suite green on MySQL | ⬜ |
| 11 Production | Production config, cron, SSL, backups, smoke tests, launch checklist | Launch checklist signed off | ⬜ |

## Key assumptions (reversible)
- Trial: 14 days on STARTER. Grace period after expiry: 7 days (platform setting).
- GST default 5% for taxi services (configurable per tenant fare rule).
- Marketplace commission default 10% (platform setting).
- Operator microsite path `/o/{slug}`.
