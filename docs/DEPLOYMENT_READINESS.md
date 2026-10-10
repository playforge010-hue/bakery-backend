# Deployment Readiness

Last reviewed: 2026-10-05

## Current verdict

The local customer and admin applications are running, and the customer home API now returns active admin-managed banners. This is a functional development build, not yet ready for public production. Payment and message delivery providers, production hosting/secrets, a full security and operational review, and launch approval remain outstanding.

## Implemented

- Laravel API with MySQL schema, seed data, Sanctum auth, shared response envelope, and `config/imagepath.php`.
- Explicit GET and POST routes for customer catalog, auth, addresses, cart, checkout, orders, location, favorites, and support.
- Server-backed checkout options, schedule availability, configured cart items, checkout summary, and order placement; totals and fulfillment rules are calculated by the API.
- Active homepage banners are configurable in the admin portal and included in customer home API data.
- Admin login/roles, dashboard, order operations, catalog/category/product options, banner content, delivery settings, coupons, staff, customers, support, and activity log APIs.
- Responsive Next.js customer and admin screens. Customer loading states now use layout-shaped skeletons with a lightweight shimmer and reduced-motion handling.
- OpenStreetMap map selection, autocomplete, browser location, draggable marker, and route display for takeaway.
- Local verification on 2026-10-05: Next.js TypeScript check passed; homepage, admin login, admin banners, and API health returned HTTP 200; customer home API returned the success envelope with 1 banner, 4 categories, and 4 featured products. No automated tests were run, as requested.

## Remaining before production

### Provider and business setup

- Select, configure, and verify a payment gateway, including signatures, idempotent webhooks, failure handling, reconciliation, and refunds.
- Configure a real SMS/OTP provider; verify throttling, expiry, attempt limits, and production-safe handling of OTP values.
- Confirm bakery business details, legal/privacy/refund content, delivery coverage and fees, hours, tax configuration, and customer notifications.

### Production infrastructure and security

- Provision production hosting, HTTPS domain, MySQL, durable media storage, queue/cache/session services, scheduler and workers, logging/monitoring, and backups with a restore rehearsal.
- Set production secrets, `APP_ENV=production`, `APP_DEBUG=false`, exact CORS origins, secure auth/session policy, and production database credentials. Do not deploy local database files or development credentials.
- Review API authorization and input boundaries, upload validation/retention, rate limits, admin role policy, dependency/security advisories, and error/log redaction.
- Decide production geocoding/routing providers and usage limits; public OSM ecosystem services need a compliant availability and fallback plan.

### Release verification

- Run the requested customer and admin flows on staging across mobile and desktop, including signup/login, product customization/photo upload, checkout, delivery eligibility, takeaway route, payment outcomes, admin order processing, media uploads, and recovery/error states.
- Add meaningful automated coverage and CI after the owner’s current instruction against running project tests is lifted.
- Validate database migrations on a clean staging database, seed only intentional production data, verify restore/rollback steps, and obtain business sign-off before launch.

## Local services

- Customer and admin website: `http://localhost:3000`
- Admin sign-in: `http://localhost:3000/admin/login`
- Laravel API health: `http://localhost:8000/api/v1/health`
- Admin owner bootstrap command: `php artisan admin:create-owner`

Keep this document and `DEVELOPMENT_TRACKER.md` synchronized after each meaningful implementation session.
