# Dwarkesh Bakers Development Tracker

Use this as the living tracker for the rebuild. Update it after every meaningful development session.

## Current Status

Project stage: Customer web ordering flow connected to MySQL APIs; operational and launch work remains
Current phase: Customer flow integration, hardening, and operations
Last updated: 2026-10-07

Coding standards alignment update (2026-10-07): the previously checked-in `docs/CODING_STANDARDS.md` described TeacherWay's separate Flutter/parent/teacher product and conflicted with Dwarkesh's API contract. Replaced it with Dwarkesh standards following the reference project's request lifecycle, layer boundaries, validation, PHPDoc, and workflow structure while retaining the bakery scope, Laravel 12 conventions, GET/POST routes, Gujarati/English/Hindi, Dwarkesh response envelope, and `config/imagepath.php`. Added customer Form Requests for address, cart, location, coupon, and catalog inputs; converted those filters/mutations to validated data; routed customer API paths directly to focused controllers; removed the unused customer controller facade; introduced fixed-value enums; added centralized Gujarati-default `X-Locale` handling; and made `/orders/place-order` a compatibility alias to the repricing checkout action. Sanctum security update: customer tokens receive `customer-access`; admin tokens receive `admin-access`, `admin:read`, and `admin:write`. Protected routes enforce matching token abilities and account roles, admin middleware checks read/write scope by HTTP method, API authentication/ability failures use the standard error envelope, and broadcast channels enforce matching scope, active status, and ownership. Existing admin tokens need a fresh login for the new ability. Updated API and coding standards documentation. PHP syntax lint passed for 46 changed PHP paths; Laravel route listing resolved 107 API routes and showed the customer/admin ability middleware. No tests were run. The pre-existing rebuild was pushed to `origin/main` as `5f4b2d1` before this standards work; follow-up alignment edits remain local.

Customer API architecture update (2026-10-05): `CustomerAppController` now forwards its established route actions to focused catalog, location, identity, address, cart, order, and engagement controllers. Shared customer response/data helpers are in `CustomerApiControllerBase`; catalog projections use explicit category/product resources and scheduled homepage banners use a banner resource and the model's active-window scope. OTP/profile/cart/order-detail/favorites/support validation is handled by customer FormRequests; customer model relationships were added. Route declarations and existing response fields are preserved, with `banners` added to homepage data. Verification: PHP syntax lint and Laravel route listing only; no tests were run.

## Global Rules Checklist

- [x] Single-bakery architecture confirmed
- [x] Marketplace and multi-store features rejected
- [x] Laravel API is the source of truth
- [x] Next.js website is active frontend priority
- [x] Flutter is paused
- [x] Only GET and POST routes allowed
- [x] No resource routes/controllers
- [x] Consistent API response trait required
- [x] `config/imagepath.php` required
- [x] Frontend responses must include only required fields
- [x] Development tracker required after every session

## Phase Progress

| Phase | Name | Status | Target Duration | Notes |
| --- | --- | --- | --- | --- |
| 0 | Reset And Rules | Complete | 1 day | Old developed code removed without backup; Flutter is paused |
| 1 | Backend Foundation | In Progress | 4-5 days | MySQL schema, seed data, Sanctum, response trait, image config, and separated customer/admin APIs exist; production hardening remains |
| 2 | Auth And Roles | In Progress | 4-5 days | Customer OTP/profile and admin role flows exist; production SMS, recovery, and security review remain |
| 3 | Catalog | In Progress | 7-9 days | Dynamic customer catalog, product detail, admin catalog, product options, image/gallery and homepage banner management exist; content/polish review remains |
| 4 | Cart And Checkout | In Progress | 7-9 days | Configured cart, live checkout summary, schedule and server-side order totals/eligibility are implemented; staging verification remains |
| 5 | Orders And Payments | In Progress | 8-10 days | Customer/admin order lifecycle exists; payment gateway, webhooks, reconciliation and refunds remain |
| 6 | Admin Operations | In Progress | 7-9 days | Admin authentication, dashboard, operations APIs and responsive management screens exist; production access/security review remains |
| 7 | Next.js Website | In Progress | 12-15 days | Customer and admin routes are connected; mobile/desktop staging review and provider-dependent flows remain |
| 8 | QA And Launch Prep | Not Started | 7-10 days | Local static and HTTP smoke checks pass; production infrastructure and launch rehearsal remain. No tests run per current user instruction |

See [DEPLOYMENT_READINESS.md](DEPLOYMENT_READINESS.md) for the verified remaining-work checklist and launch blockers.

### 2026-10-05 - Customer Skeletons And API Architecture

- Added responsive, content-shaped skeletons across customer route loading states and data-loading sections on the homepage. The shimmer is CSS-only and disables motion for reduced-motion preferences.
- Extracted customer catalog shaping into a service/resources and split existing customer endpoint behavior across focused controllers while retaining established route actions and response fields.
- Added FormRequests for previously inline customer request validation and eager banner data to the homepage API; banners created or scheduled in admin now appear in the customer home response.
- Fixed stale Next.js webpack output after `/` failed with a missing generated chunk. Restarted Next using a fresh `.next` output directory; kept the old cache directory as `.next-stale`.
- Verified TypeScript, customer PHP syntax, customer home API envelope/data, homepage/admin login/admin banners/API health HTTP responses. No project test suites were run, per user instruction.
- See [DEPLOYMENT_READINESS.md](DEPLOYMENT_READINESS.md) for actual production blockers. Older checklist sections below contain historical planning entries; use this status table and deployment document as the current status.

## Phase 0 Checklist

- [x] Read single-bakery planning note
- [x] Inspect backend/frontend/mobile project folders
- [x] Create agent coding rules
- [x] Create rebuild development plan
- [x] Create development tracker
- [x] Remove unused .kilo tooling folder
- [x] Create database design document from single-bakery planning note
- [ ] Backup skipped by user request
- [ ] Backup skipped by user request
- [ ] Decide whether to keep current Git history or create fresh repositories
- [x] Remove old developed backend feature code
- [x] Remove old developed frontend feature code
- [ ] Keep Flutter unchanged

## Backend Foundation Checklist

- [ ] Clean Laravel feature code while preserving skeleton
- [ ] Confirm Laravel version and dependency baseline
- [ ] Configure Sanctum for API token auth
- [ ] Configure CORS for Next.js
- [x] Create `config/imagepath.php`
- [x] Create `App\Traits\ApiResponseTrait`
- [ ] Configure validation/exception API error shape
- [ ] Create base enums/constants
- [ ] Create business settings migrations/models/resources
- [ ] Create delivery zone and slot migrations/models/resources
- [x] Create seeders for initial bakery data
- [ ] Add first backend tests for response shape and settings APIs

## Auth And Roles Checklist

- [ ] Admin login/logout APIs
- [ ] Customer send/verify OTP APIs
- [ ] Customer profile APIs
- [ ] Customer address APIs
- [ ] Staff role permissions
- [ ] Auth middleware checks
- [ ] Tests for auth and protected routes

## Catalog Checklist

- [ ] Category migrations/models/requests/resources/controllers
- [ ] Product migrations/models/requests/resources/controllers
- [ ] Product images using image config
- [ ] Product variants/weights/flavours/add-ons
- [ ] Egg/eggless support
- [ ] Cake customization options
- [ ] Inventory and sold-out controls
- [ ] Product search and filters
- [ ] Admin catalog APIs
- [ ] Customer catalog APIs
- [ ] Tests for catalog flows

## Cart And Checkout Checklist

- [x] Cart database APIs
- [ ] Cart item customization
- [ ] Custom cake message
- [ ] Customer image upload validation
- [ ] Delivery availability check
- [ ] Takeaway flow
- [ ] Schedule date/time validation
- [ ] Coupon validation
- [ ] Checkout summary API
- [ ] Tests for cart and checkout validation

## Orders And Payments Checklist

- [x] Order placement and status history foundation
- [ ] Order item snapshot data
- [ ] Order status history
- [ ] Admin order actions
- [ ] Payment intent API
- [ ] Payment verification API
- [ ] Payment webhook log
- [ ] Invoice data
- [ ] Cancellation rules
- [ ] Refund foundation
- [ ] Tests for order placement and status changes

## Next.js Website Checklist

- [ ] Clean old UI code after backup
- [x] Create API client
- [ ] Create typed response helpers
- [ ] Create shared layout and UI components
- [ ] Home page
- [ ] Category/product listing
- [ ] Product detail and customization
- [ ] Cart
- [ ] OTP login
- [ ] Address/location check
- [ ] Checkout
- [ ] Payment result
- [ ] Order tracking
- [ ] Order history and reorder
- [ ] Build/lint checks

## Session Log

### 2026-10-07 - Coding Standards Alignment

Project touched: Laravel backend documentation and customer API

Completed:

- Replaced the TeacherWay-specific coding guide with Dwarkesh backend standards based on its architecture and request-flow conventions.
- Added endpoint-specific Form Requests for customer catalog filters, delivery lookup, addresses, cart mutations, and coupons. Controllers now consume validated fields for these inputs.
- Added centralized Gujarati-default API locale selection through the `X-Locale` header and aligned translation fallback to `gu`, then `en`, then `hi`.
- Routed customer API endpoints directly to their focused controllers and removed the no-longer-used compatibility façade; route URLs and methods remain unchanged.
- Added enums for order/payment states, fulfillment, payment methods, coupon types, and support ticket states; applied them to checkout validation and order transitions.
- Added Sanctum customer/admin abilities, role and method-specific scope checks, standard API errors for auth/scope failures, and matching broadcast-channel checks.
- Updated API contract notes and this tracker. A backup branch named `backup/coding-standards-before-dwarkesh-20261007` and a temporary copy of the replaced document were created before the edit.

Validation:

- PHP syntax lint passed for 46 changed PHP paths. `php artisan route:list --path=v1` resolved 107 routes, and verbose customer/admin route listings showed the expected Sanctum abilities and role middleware. No tests were run.
- The earlier rebuild commit `5f4b2d1` was pushed to `origin/main` before these follow-up changes. The standards alignment changes remain uncommitted and unpushed.

Remaining:

- Complete the wider controller-to-action/service refactor for existing customer flows that still contain database queries and multi-table operations in controllers.

### 2026-10-04 - Planning And Agent Rules

Project touched: Backend and frontend documentation

Completed:

- Read the existing single-bakery planning note from Desktop.
- Inspected the three project folders.
- Confirmed Laravel backend and Next.js frontend are the active rebuild targets.
- Created full rebuild plan, agent rules, API standards, frontend plan, database design, and tracker.

Tested:

- No application tests run because this session changed planning documentation and removed unused tooling only.

Next task:

- Create backup branch/tag for current backend and frontend.
- Start Phase 0 cleanup by removing old developed feature code in controlled batches.

Open decisions:

- Confirm payment provider: Razorpay, PhonePe, Cashfree, manual UPI, COD, or mixed.
- Confirm SMS/WhatsApp OTP provider.
- Confirm exact business address, coordinates, phone, WhatsApp, GST/FSSAI, opening hours, delivery zones, and minimum order.
- Confirm whether admin UI will be Next.js later or API-only first.


### 2026-10-04 - UI First Direction

Project touched: Frontend and backend scaffold

Completed:

- User requested old code removal without backup.
- Removed current developed Laravel and Next.js feature code.
- Kept Flutter untouched.
- Started Next.js home page UI first using the Swiggy screenshot structure as reference, changed to Dwarkesh Bakers single-bakery green/white theme.
- Routes added: /, /cakes, /product/chocolate-truffle-cake, /cart, /checkout, /orders, /account.

Testing:

- Next.js production build passed after all customer UI routes were added. Local dev routes returned HTTP 200.

Next task:

- Continue polishing responsive UI screens first.
- Create Laravel API contracts according to the completed UI data needs.
- Implement APIs after UI structure is approved.


### 2026-10-04 - Full Customer Web Shell And API Contracts

Project touched: Laravel API and Next.js frontend

Completed:

- Added Laravel customer API response trait and customer app controller.
- Added 30 explicit GET/POST customer API routes for bootstrap, home, categories, products, location, OTP auth, profile, addresses, cart, checkout, orders, coupons, favorites, and support.
- Added frontend API endpoint map in `lib/api.ts`.
- Added shared mock data in `lib/mock-data.ts` for UI/API alignment while database-backed services are built.
- Added customer screens: login, register, OTP verification, location selector, saved addresses, profile, favorites, support, order history.
- Kept all flows single-bakery for Dwarkesh Bakers only.

Testing:

- `php artisan route:list --path=api/v1` passed and showed 30 API routes.
- Laravel local API endpoints returned HTTP 200 for health, bootstrap, home, product list, and order list.
- `npm run build` passed and generated 17 frontend routes.

Next task:

- Replace static Laravel API arrays with database migrations, seeders, models, requests, services, and resources in phases.
- Connect frontend forms to API calls with client-side submit states and validation messages.
- Add admin screens after the customer web flow is stable.

### 2026-10-04 - MySQL Schema And Real Customer Mutations

Completed:

- Added one ordered MySQL migration covering the complete single-bakery schema.
- Added seeded bakery, catalog, customer, cart, order, delivery, coupon, banner, and support data.
- Configured Laravel for MySQL database `dwarkesh_bakers`.
- Added `config/imagepath.php` for all image and upload directories.
- Implemented database-backed cart, address, cancellation, and reorder mutations.
- Cleared the generated Next.js `.next` cache and restarted the frontend development server.

Testing:

- PHP syntax checks passed for the controller and image configuration.
- Laravel route list passed with 30 explicit GET/POST routes.
- Next.js production build passed with 17 customer routes.
- `/` and `/checkout` returned HTTP 200 after the cache cleanup.
- `php artisan migrate:status` is ready but cannot connect until the local MySQL root password is supplied; current `.env` uses root with an empty password and MySQL returned error 1045.
- Created a project-local MySQL 8 instance on port 3307 because the existing Windows MySQL80 service could not be administered from this session; migrations and seed data now run successfully there.
- Added OpenStreetMap tiles, Photon autocomplete, browser geolocation, and OSRM road-route drawing for Jamnagar takeaway location selection.
- Added tap-to-place and drag-to-adjust map pin behavior, reverse geocoding for readable addresses, and browser persistence of the selected location.
- Connected map coordinates to the address form and checkout; checkout now submits delivery/takeaway, payment choice, and selected address data to Laravel.
- Order placement now stores delivery/pickup snapshots, order item and add-on snapshots, pending payment records, status history, and clears the cart in one database transaction.

Next task:

- Keep the project-local MySQL instance available on port 3307 during local development, or later move the same schema to the managed MySQL service.
- Replace the local seeded-customer lookup with Sanctum-authenticated customer middleware before production deployment.

### 2026-10-05 - Cart Price Trust Fix

Completed:

- Added server-side validation for product, active variant ownership, quantity limits, fulfillment, and cake message fields on cart item creation.
- Cart unit prices now come only from the active database variant or product base price; browser-submitted prices are ignored.
- Verified through the running API using a signed-in seeded customer: a request sending `unit_price: 0.01` for a Rs. 499 product created a Rs. 499 cart line.

Validation:

- Laravel syntax check and automated tests rerun after this change.

Next task:

- Keep reviewing cart, checkout and delivery calculations for client-controlled totals; then add focused feature tests around authentication, authorization and price integrity.

### 2026-10-05 - Signed-In Customer Header

Completed:

- Homepage navigation now replaces Sign In / Register with the signed-in customer's avatar and name, linking to the profile.
- Customer avatar URL is included in verify-OTP and profile API responses when an avatar has been uploaded; initials display as a fallback.
- Session changes emit a same-tab browser event so the header responds immediately to login/logout changes.

Validation:

- Laravel syntax check and 2 existing Laravel tests passed.
- TypeScript check passed.
- `npm run build` passed for all 17 pages.
- Both local services are being restarted on their established URLs.

### 2026-10-05 - Customer Profile Avatar Upload

Completed:

- Added authenticated profile-photo upload using the shared `config/imagepath.php` disk and user avatar directory.
- Validates JPG, JPEG, PNG, or WebP images up to 2 MB, replaces the previous stored photo, and returns the public avatar URL from profile APIs.
- Added profile photo preview and save support to the Next.js profile page; the homepage signed-in header uses the uploaded photo and customer's name.
- Created Laravel's public storage link for local image serving.

Validation:

- PHP syntax check passed; Laravel's two existing skeleton tests passed.
- TypeScript check and production build passed with all 17 pages.
- Full multipart upload was not exercised end-to-end in this session; API validation and storage integration should receive a dedicated feature test.
### 2026-10-05 - Profile Drawer Interaction

Completed:

- Changed the signed-in homepage profile control from a route link to a button that opens an animated left-side modal drawer.
- Added a translucent blurred scrim over the home page, frosted drawer surface, customer identity, edit-profile shortcut, order/address/favorites/support destinations, and sign-out action.
- Added scrim click, Escape, focus containment/restoration, body scroll lock, and reduced-motion handling.

Validation:

- `npx tsc --noEmit` passed.
- `npm run build` passed and generated all 17 app routes.
- Restarted the production preview server; homepage returns HTTP 200.
### 2026-10-05 - Profile Page Visual Refresh

Completed:

- Rebuilt the customer profile screen as a responsive green-and-white account panel with clear personal details and shortcuts.
- Replaced the visible native upload field with a circular avatar and floating pencil action. Selecting a supported image previews and uploads it immediately with inline progress and feedback.
- Added client-side type/size checks, accessible button labeling, and a keyboard-usable hidden picker.

Validation:

- TypeScript check and Next.js production build passed; all 17 routes generated.
- Restarted the local production preview after the build.

### Customer Checkout And Ordering Workstream

Implemented in this worktree:

- Added a dedicated customer checkout controller, Form Requests, a checkout resource, Eloquent catalog/cart/order models, a checkout pricing/scheduling service, and actions for configured cart lines and atomic order placement.
- Added trusted pricing for active variants, flavour increments and add-ons; coupon eligibility/caps; location/radius-based delivery zones, minimums and fees; tracked stock checks/decrement; and schedule checks for opening/holiday hours, product prep/notice, schedule horizon and capacity.
- Added reference-image upload to the configured product-custom-upload directory and included upload metadata in order item snapshots.
- Replaced the fixed product URL with dynamic `/product/[slug]`; the product screen loads category/options and submits option IDs rather than prices.
- Replaced fixed checkout sample totals and the hard-coded schedule with live API summary, available slot selection, delivery/map address, pickup/delivery, customer note, and final order confirmation.
- Refactored checkout reads and writes to Eloquent models and relationships for products, categories, variants, flavours, add-ons, inventory, carts, addresses, zones, schedules, coupons, orders, payments, histories and uploads. The schema stores opening hours, holidays and slots as global single-bakery tables without a business-setting foreign key, so those are queried directly through their Eloquent models by weekday/date/fulfillment type.
- The exact route and payload table is in `API_STANDARDS.md`. At the latest workspace inspection, the customer checkout routes were present in `routes/api.php` from parallel workspace changes.

Verification for this slice: PHP syntax checks passed. TypeScript/build verification is in progress. No tests were added or run, as requested.

### 2026-10-05 - Admin API Backend Foundation

Implemented in additive admin-focused backend files:

- Admin/owner/staff Sanctum login and logout, active-role middleware, scoped bearer tokens, and a one-time interactive `admin:create-owner` Artisan command. There is no public owner-registration route.
- Dashboard metrics; editable business settings and configured logo/cover uploads; category/product CRUD, gallery, variants, flavour/add-on relationships and definitions, customization fields, stock and sold-out controls.
- Delivery/takeaway order lists/details and atomic state transitions, coupons, delivery zones, opening hours, holidays and slots, customer status, support conversations, staff provisioning/status, and audit event reads/writes.
- Eloquent relationships/casts/scopes, image URL accessors and `config/imagepath.php` defaults for admin-facing model images.
- Admin Form Requests that emit the common validation error envelope. The admin controllers contain no inline `$request->validate()` calls.
- Production guard in `DatabaseSeeder`: demo users/products/orders and the weak `password` seed credentials are skipped when `APP_ENV=production`.
- Exact route/controller/payload map: `docs/ADMIN_API_CONTRACT.md`.

Schema review: existing tables already support the requested admin workflows; no migration was needed. `routes/api.php` was not edited as part of this implementation. At final inspection it already contained 62 GET/POST admin routes registered by parallel workspace work, and `php artisan route:list --path=api/v1/admin` resolved them. Login now rate-limits failed attempts in the controller by identifier and IP. Staff write routes use `admin.role:owner,admin`; ordinary staff access is restricted by the role middleware to order/support actions. The parallel Next.js admin UI currently lacks dedicated screens for global flavours/add-ons, delivery zones, holidays, and delivery slots; those backend routes are available for that integration.

Verification: PHP syntax lint, Artisan command discovery, and `php artisan route:list --path=api/v1/admin` passed. No tests were added or run per instruction. No end-to-end requests were run.

### 2026-10-08 - Admin operations, delivery pricing, and support follow-through

Completed in the shared workspace:

- Added explicit customer and admin support chat routes for ticket lists/details, replies, read receipts, typing indicators, status changes, and authenticated image attachments. Private Reverb channels enforce customer ticket ownership and admin role access.
- Registered distance-based delivery fee range management endpoints and switched the customer delivery availability check to the distance pricing service, including maximum-range enforcement.
- Completed dispatcher status queues/actions, tab counts, order details, order list filters/exports, dashboard date filtering and charts, and non-technical catalog management screens in the frontend.
- Added the support-chat and distance-pricing schema migrations and applied them to the configured MySQL database.
- Updated the admin API contract with the dispatcher, delivery-pricing, export, and support routes.

Verification completed: migrations applied; Artisan route discovery and focused API smoke checks passed; frontend TypeScript checking and production build passed. The automated test suite was not run, per the project instruction. Live two-user chat, real browser notifications on physical devices, and a full customer checkout-to-dispatch workflow still need hands-on acceptance against the running services before deployment sign-off.

### 2026-10-09 - Customer ordering flow audit

Completed:

- Normalized product, category, product detail, cart, and customer avatar image URLs through the shared image path helper; removed the fake product rating and the unused sample catalog/address data.
- Replaced hard-coded map origin coordinates with the bakery coordinates from app bootstrap. Map search bias and route drawing now use the configured bakery address and map point.
- Rebuilt the product listing filters around live category and product APIs; removed decorative filters and sample product imagery.
- Made saved addresses server-backed and removed the fabricated receiver, mobile number, city, state, and pincode defaults.
- Updated cart rows to show the catalog product image, made coupon entry call the API, validate eligibility, and persist the applied coupon. Basket changes clear the coupon so an old discount is not displayed.
- Fixed cart quantity updates to preserve add-on prices. Checkout now uses the selected map point when requested, reads pickup details and order-type availability from bakery settings, and fails closed when settings cannot load.
- Selected the configured available default product variant and made gallery thumbnails interactive. A customer reference upload preview no longer replaces the catalog product image.
- Replaced legacy customer zone/pincode availability checks with the same distance pricing service used by checkout.
- Disabled Card and UPI in customer checkout and rejected non-cash order placement until a payment gateway can verify capture. The admin settings page now explains this limitation.
- Made the development OTP visible only when the API returns a local testing code; the API no longer advertises the log-only OTP flow as production-ready.

Verification:

- `npx tsc --noEmit` passed after the final frontend edits.
- PHP syntax checks passed for the changed services, actions, requests, and controllers.
- `php artisan route:list --path=api/v1` registered all 121 API routes.
- No tests were added or run, per the project instruction.
- At the time of this audit, customer APIs were blocked by local MySQL authentication. This was resolved in the 2026-10-09 local database setup entry below.

Deployment blockers still open:

- Configure a production SMS/OTP delivery provider; the current OTP sender is log-only.
- Integrate a payment gateway and its verified callback/webhook before enabling UPI or card.
- Set production environment values and secrets, database credentials, HTTPS URLs, public storage/CDN base URL, allowed CORS origins, mail delivery, Reverb credentials, and a supervised queue worker.
- Run migrations against the deployment database, configure distance ranges, store coordinates, hours, schedules, delivery/takeaway availability, cash availability, product catalog, and image storage.
- Complete browser acceptance for customer checkout, delivery rejection/range fees, pickup scheduling, coupon redemption, order tracking updates, admin dispatch, and real Reverb chat/notifications.
- Re-run type checking, PHP lint, production build, and the operator's requested release checks after the DB and service configuration is available.

### 2026-10-09 - Local database, admin access, and storefront demo catalog

Completed:

- Restored the Laravel app's local MariaDB connection on port 3307, created the missing `dwarkesh_bakers` database, and applied all three migrations. Fixed the initial schema migration so MariaDB accepts the non-null `orders.placed_at` timestamp.
- Created the first owner through the private `admin:create-owner` Artisan command. The local development login form now reads autofill values from the frontend `.env.local`; no credentials are hard-coded in tracked frontend source.
- Replaced the legacy all-in-one demo seeder with an idempotent storefront seeder. It adds six categories, ten products, 25 variants, flavours, add-ons, customization options, inventory rows, bakery hours, delivery slots, the existing distance-fee ranges, and a homepage banner. It does not fabricate sales, reviews, customers, or orders.
- Downloaded seven Unsplash bakery photos into tracked seeder assets and documented their source and demo-only use in `docs/LOCAL_DEMO_CATALOG.md`. The seeder copies them to public storage for local serving.
- Kept the app and database services available on the LAN at `http://10.227.204.112:3000` and `http://10.227.204.112:8000`.

Verification:

- Admin API login returned success with the owner role; the local login page returned HTTP 200 and rendered its configured autofill values.
- The home API returned HTTP 200 with six categories and five featured products. All seven seeded images returned HTTP 200 through the LAN address.
- Re-running the seeder kept the catalog counts stable and did not add demo orders.
- Laravel Pint, PHP syntax checks, and `npx tsc --noEmit` passed. No automated test suite was run.

Local demo data still needs the bakery owner to confirm actual product prices, product photos, store contact details, and the exact map pin before any public launch.

### 2026-10-09 - Homepage location permission and map pin

Completed:

- The homepage now requests browser location access on first visit when no delivery location was explicitly chosen. A granted location is reverse-geocoded when available, stored with coordinates, and used as the selected delivery point. Existing manual selections are preserved; GPS-based selections refresh on later visits.
- Removed the road route and bakery circle marker from the customer location selector. Search, map taps, draggable selection, and the current-location button now use one draggable green map pin.
- Updated location-page copy to describe address selection rather than route planning. Takeaway route behavior elsewhere is unchanged.

Verification: `npx tsc --noEmit` passed. Browser geolocation acceptance was not run. Mobile browsers require a secure context (HTTPS; localhost is allowed) for geolocation, so the current HTTP LAN URL may not show a permission prompt.

### 2026-10-09 - Shared dropdown styling

Completed:

- Added a reusable accessible dropdown with consistent Dwarkesh styling, responsive width, keyboard navigation, outside-click dismissal, disabled options, and selected-option indication.
- Replaced native selects throughout customer checkout/support and admin date filters, catalog/product forms, order filters, resource forms, and support inbox controls.

Verification: `npx tsc --noEmit` passed. No automated tests were run.

### 2026-10-09 - Mobile location map sizing

- Reduced the location picker map to a viewport-aware height on phones and retained larger fixed map heights on tablet and desktop.
- Verification: `npx tsc --noEmit` passed.

### 2026-10-09 - Customer and admin visual consistency pass

Completed:

- Unified customer shopping, checkout, profile, address, support, location, and order pages on the same soft green-tinted canvas used by the Dwarkesh visual system.
- Added consistent mint borders for customer cards/forms and controls, plus a visible emerald keyboard-focus ring and reduced-motion-aware button transitions.
- Updated customer skeleton product grids to match the live two-column mobile layout and expand to three/four columns at larger breakpoints. Loading page backgrounds now match their finished pages.
- Reviewed admin navigation and screen color usage; the existing admin shell already uses a consistent emerald, white, and slate palette.

Verification:

- `npx tsc --noEmit` passed.
- No automated tests were run, per the project instruction.
- Visual browser review is still needed on customer routes and the admin console at phone, tablet, and desktop widths.
- Live route smoke check returned HTTP 200 for the homepage, all customer routes, the admin login, and the admin dashboard. A production build was skipped because the customer dev server is running and shares the `.next` directory.

### 2026-10-09 - Product detail loading flash

- Fixed the brief false “Product not found” state during product navigation by binding the detail loading state to the active slug and ignoring stale request completions.
- Verification: `npx tsc --noEmit` passed; `/product/photo-print-cake` returned HTTP 200 from the running Next.js app.

### 2026-10-09 - Product detail navigation motion

- Product links now leave scroll handling to the detail route, which smoothly scrolls to the top as the slug changes. The loaded detail view uses a short, subtle entrance fade and slight lift; both scrolling and entrance motion honor reduced-motion preferences.
- Verification: `npx tsc --noEmit` passed; the running detail route returned HTTP 200. Used Next.js Link's documented `scroll={false}` behavior so page code controls the smooth scroll.

### 2026-10-09 - Smooth skeleton-to-content reveals

- Added a shared short fade-in for customer pages after their page skeleton is replaced, plus restrained staggered reveals for homepage/catalog cards and delivery-area data that load section by section.
- Added the same subtle reveal to admin cards/table rows and customer support ticket/message groups so fetched content does not pop into place.
- Kept movement limited to small content blocks, preserved layout geometry, and disabled the animations for users who prefer reduced motion.
- Verification: `npx tsc --noEmit` passed; homepage, catalog, cart, checkout, product detail, support, and admin routes all returned HTTP 200 from the running dev server. No automated tests were run.

### 2026-10-09 - Mobile checkout delivery layout

- Constrained the checkout grid and delivery address card for narrow screens, added safe wrapping for long saved-address labels/text, and stacked the map/address actions into full-width touch targets on phones.
- Increased bottom content clearance and added device safe-area padding to the fixed checkout action so it does not cover delivery form content on phones with home indicators or multiline status text.
- Verification: `npx tsc --noEmit` passed; `/checkout` returned HTTP 200 from the running dev server. Manual device-width visual review remains necessary.

### 2026-10-10 - Repository and production environment preparation

- Prepared separate backend and frontend repositories with safe environment examples and deployment notes.
- Replaced the backend's blanket CORS settings with configurable allowed origins and explicit API methods; local development remains open by default, while production should set exact HTTPS origins.
- Prevented the demo catalog seeder from running under `APP_ENV=production`.
- Production customer OTP delivery remains blocked until an SMS provider is connected. The production example deliberately selects a non-log driver so the API fails closed instead of logging live OTPs.
- Verification: PHP syntax and API configuration checks completed. Automated tests were not run, per the project instruction.

### 2026-10-10 - Railway environment copy template

- Made `.env.example` the Railway backend Raw Editor template with the configured API domain, port, MySQL service references, image URL, logging, and safe production defaults. Kept `.env.local.example` for local setup and aligned Composer's local setup scripts with it.
- Documented the required private `APP_KEY`, exact frontend CORS origin, persistent upload storage, and the current SMS, mail, and Reverb limitations in the README. Removed the duplicate production example to avoid conflicting templates.
- Verification: environment keys and Composer JSON checked; no automated tests were run, per the project instruction.

### 2026-10-10 - Railway GD build fix

- Declared the GD PHP extension in Composer's root requirements because the locked PDF dependency requires it. Railpack reads root platform requirements when provisioning PHP extensions, so the Railway build should install GD before running Composer.
- Verification: Composer configuration and lockfile validation completed; no automated tests were run, per the project instruction. Railway rebuild is needed to confirm the hosted environment.

### 2026-10-10 - Railway libatomic1 package

- Added a minimal `railpack.json` overlay to include `libatomic1` in Railpack's Apt packages without replacing its defaults. The PHP builder carries its Apt packages into the final runtime image.
- Verification: JSON syntax and Git diff checked; hosted rebuild is needed to verify Railway's package installation.

### 2026-10-10 - Remove unused backend Vite build

- Removed the backend's unused `package.json`, lockfile, and Vite configuration after Railway attempted to compile a missing `resources/css/app.css` entry point. This is an API-only repository; the customer/admin Next.js build remains in the frontend repository.
- Removed obsolete npm steps from Composer's local setup/development scripts so Railpack will build only the Laravel application.
- Verification: Composer JSON and PHP route configuration checked; hosted rebuild is needed to confirm the Railway build. No automated tests were run, per the project instruction.

### 2026-10-10 - Railway public port alignment

- Set the Railpack runtime `PORT` default to 8000 to match the backend public domain's target port after the live health endpoint returned Railway 502 while FrankenPHP reported its server running.
- The frontend's local environment now targets the Railway API. Public health and home API responses must be rechecked after the backend redeploys; Railway deploy logs remain the source of truth if 502 persists.

### 2026-10-10 - MySQL driver and Railway port correction

- Railway's automatically detected target port 8080 restored the live health endpoint, so removed the hardcoded port-8000 image default and environment example.
- Live public catalog and bootstrap endpoints still returned 500 while the database-free health endpoint returned 200. Declared the MySQL PDO extension explicitly for the Railway PHP image; deployment logs are still needed to confirm whether a missing driver or database configuration is the actual 500 cause.

### 2026-10-11 - Railway environment copy correction

- Updated the public `.env.example` for the actual API domain, valid Railway MySQL variable references, current local and mobile CORS origins, and safe log-based broadcast and mail defaults. Removed unused blank Reverb and SMTP settings to avoid suggesting those integrations are configured.
- Prepared a Git-ignored `.env.railway.copy` locally with a unique generated `APP_KEY` for the Railway Raw Editor. The secret is intentionally absent from the public example and Git history.
- The hosted environment still needs the copied variables applied and redeployed. The existing production SMS driver has no provider, live Reverb is not configured, and uploaded files need persistent storage before the full site can be considered production ready.

### 2026-10-11 - Railway database migration on deploy

- Verified the public health route returns 200 and the corrected CORS origin is active, while catalog and bootstrap routes still return 500. These failing routes query MySQL, but the public response does not expose the database exception.
- Added Railway's supported pre-deploy migration command so fresh deployments apply the schema before serving traffic, or fail with a clear migration/database error in the pre-deploy logs. This requires the Railway MySQL service references to resolve and the database to be reachable.
- Verification: checked the JSON configuration and public routes; hosted deployment results still need to be observed. No automated tests were run, per the user's instruction.
