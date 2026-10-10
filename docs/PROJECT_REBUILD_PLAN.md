# Dwarkesh Bakers Full Rebuild Plan

Status: Planning complete, implementation not started
Owner: Dwarkesh Bakers
Primary stack: Laravel 12 API, Next.js 14 website, Flutter later
Architecture: Single bakery, single seller, shared API

## Product Decision

Dwarkesh Bakers is one bakery platform, not a marketplace. Customers order only from Dwarkesh Bakers. The platform supports delivery and takeaway, but there is no store selection, vendor registration, commission, payout, multi-merchant cart, restaurant comparison, or vendor dashboard.

The Laravel backend must serve the current Next.js website and the later Flutter app through one clean API.

## Projects

- Backend API: `D:\laravel\dwarkesh-bakers`
- Website: `D:\laravel\dwarkesh-bakers-frontend`
- Flutter app: `D:\laravel\dwarkesh_bakers_flutter`

Flutter is paused for now. Do not modify it until the backend API and Next.js website are stable.

## Reset Rule

The existing developed code is reference only. Rebuild cleanly from scratch in planned phases.

Before removing or replacing files:

1. Commit or tag the current code state.
2. Create backup branches such as `backup/pre-rebuild-2026-10-04`.
3. Remove old feature code in controlled batches.
4. Keep framework skeleton, dependency files, environment examples, and Git history unless a fresh repository is explicitly selected.

## Coding Standards

### Laravel

- Use Laravel 12 conventions and best practices.
- Use explicit `GET` and `POST` routes only.
- Do not use `PUT`, `PATCH`, `DELETE`, `Route::resource()`, or `Route::apiResource()`.
- Name controller methods by business work, for example `updateProductCategory()`, `markOrderAsPreparing()`, `placeCustomerOrder()`.
- Use Form Request classes for validation.
- Keep controllers thin and move business logic to services/actions/classes.
- Use traits only for cross-cutting behavior such as API responses and image path helpers.
- Use API Resources or transformer classes. Do not return raw Eloquent models.
- Return only frontend-required fields.
- Use `config/imagepath.php` for all image folders and public URL path decisions.
- Add model accessors for image URL attributes such as `image_url`, `logo_url`, `gallery_urls`, and `avatar_url`.
- Add short PHPDoc comments to controllers, controller methods, Form Requests, services/actions, and complex model relationships.
- Use database transactions for checkout, payment, inventory, coupon usage, refunds, and order status changes.
- Use enums or constants for statuses and fixed types.

### Next.js

- Build the actual ordering website, not a marketing-only landing page.
- Keep the UI single-bakery only: delivery or takeaway from Dwarkesh Bakers.
- Centralize API client, response parsing, auth token handling, locale, cart state, and error handling.
- Use shared components for layout, forms, product cards, buttons, modals, loaders, alerts, empty states, and status badges.
- Do not duplicate backend business rules except lightweight user convenience validation.

## Backend Structure

Required folders:

- `app/Actions`
- `app/Services`
- `app/Enums`
- `app/DTOs` or `app/Data`
- `app/Traits`
- `app/Http/Controllers/Api/V1/Customer`
- `app/Http/Controllers/Api/V1/Admin`
- `app/Http/Requests/Api/V1/Customer`
- `app/Http/Requests/Api/V1/Admin`
- `app/Http/Resources/Api/V1/Customer`
- `app/Http/Resources/Api/V1/Admin`

Required shared files:

- `config/imagepath.php`
- `App\Traits\ApiResponseTrait`
- API exception/validation response handling
- Base enums/constants for order, payment, fulfillment, product, coupon, and staff role statuses

## Database Design Document

Detailed database tables, relationships, image path mapping, fulfillment rules, and migration order are maintained in docs/DATABASE_DESIGN.md.

## Database Modules

Foundation:

- users
- roles or role constants
- personal_access_tokens
- business_settings
- opening_hours
- delivery_zones
- delivery_slots

Catalog:

- product_categories
- products
- product_images
- product_variants
- product_weights
- product_flavours
- product_addons
- product_customization_options
- product_inventory
- product_availability_overrides

Customer:

- customers or users with customer role
- customer_otps
- customer_addresses
- customer_favorites
- customer_notifications
- customer_devices later for mobile push

Cart and checkout:

- carts
- cart_items
- cart_item_customizations
- uploaded_customization_images

Orders:

- orders
- order_items
- order_item_customizations
- order_status_histories
- invoices
- order_reviews
- support_tickets
- support_messages

Offers and payments:

- coupons
- coupon_redemptions
- payments
- refunds
- payment_webhook_logs

## API Route Plan

All routes use only `GET` and `POST`.

Customer/public API:

- `GET /api/v1/app/bootstrap`
- `GET /api/v1/home/get-home-data`
- `GET /api/v1/categories/get-category-list`
- `GET /api/v1/products/get-product-list`
- `GET /api/v1/products/get-product-detail`
- `POST /api/v1/location/check-delivery-availability`
- `POST /api/v1/auth/send-otp`
- `POST /api/v1/auth/verify-otp`
- `POST /api/v1/auth/logout-customer`
- `GET /api/v1/customer/get-profile`
- `POST /api/v1/customer/update-profile`
- `GET /api/v1/customer/addresses/get-address-list`
- `POST /api/v1/customer/addresses/create-address`
- `POST /api/v1/customer/addresses/update-address`
- `POST /api/v1/customer/addresses/delete-address`
- `GET /api/v1/cart/get-cart-detail`
- `POST /api/v1/cart/add-cart-item`
- `POST /api/v1/cart/update-cart-item`
- `POST /api/v1/cart/remove-cart-item`
- `POST /api/v1/cart/clear-cart`
- `POST /api/v1/checkout/validate-checkout`
- `POST /api/v1/orders/place-order`
- `GET /api/v1/orders/get-order-list`
- `GET /api/v1/orders/get-order-detail`
- `POST /api/v1/orders/cancel-order`
- `POST /api/v1/orders/reorder-order`
- `POST /api/v1/coupons/apply-coupon`
- `POST /api/v1/payments/create-payment-intent`
- `POST /api/v1/payments/verify-payment`
- `POST /api/v1/reviews/create-review`
- `POST /api/v1/support/create-ticket`

Admin API:

- `POST /api/v1/admin/auth/login-admin`
- `POST /api/v1/admin/auth/logout-admin`
- `GET /api/v1/admin/dashboard/get-dashboard-summary`
- `GET /api/v1/admin/settings/get-business-settings`
- `POST /api/v1/admin/settings/update-business-settings`
- `GET /api/v1/admin/categories/get-category-list`
- `POST /api/v1/admin/categories/create-category`
- `POST /api/v1/admin/categories/update-category`
- `POST /api/v1/admin/categories/update-category-status`
- `POST /api/v1/admin/categories/delete-category`
- `GET /api/v1/admin/products/get-product-list`
- `POST /api/v1/admin/products/create-product`
- `POST /api/v1/admin/products/update-product`
- `POST /api/v1/admin/products/update-product-category`
- `POST /api/v1/admin/products/update-product-status`
- `POST /api/v1/admin/products/delete-product`
- `POST /api/v1/admin/products/upload-product-image`
- `POST /api/v1/admin/products/delete-product-image`
- `GET /api/v1/admin/orders/get-order-list`
- `GET /api/v1/admin/orders/get-order-detail`
- `POST /api/v1/admin/orders/accept-order`
- `POST /api/v1/admin/orders/mark-order-as-preparing`
- `POST /api/v1/admin/orders/mark-order-as-ready`
- `POST /api/v1/admin/orders/mark-order-as-out-for-delivery`
- `POST /api/v1/admin/orders/mark-order-as-delivered`
- `POST /api/v1/admin/orders/mark-order-as-picked-up`
- `POST /api/v1/admin/orders/cancel-order`
- `GET /api/v1/admin/coupons/get-coupon-list`
- `POST /api/v1/admin/coupons/create-coupon`
- `POST /api/v1/admin/coupons/update-coupon`
- `POST /api/v1/admin/coupons/update-coupon-status`
- `POST /api/v1/admin/coupons/delete-coupon`

## API Response Shape

Success:

```json
{
  "success": true,
  "message": "Readable success message.",
  "data": {},
  "meta": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Readable error message.",
  "errors": {},
  "meta": {}
}
```

Validation:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field_name": ["Validation message."]
  },
  "meta": {}
}
```

## Image Path Plan

Create `config/imagepath.php`:

```php
return [
    'business' => [
        'logo' => 'business/logo',
        'cover' => 'business/cover',
    ],
    'category' => [
        'image' => 'categories',
    ],
    'product' => [
        'main_image' => 'products/main',
        'gallery' => 'products/gallery',
        'custom_uploads' => 'orders/custom-uploads',
    ],
    'banner' => [
        'image' => 'banners',
    ],
    'review' => [
        'image' => 'reviews',
    ],
    'payment' => [
        'proof' => 'payments/proofs',
    ],
    'user' => [
        'avatar' => 'users/avatars',
    ],
];
```

## Timeline

Estimated total: 8 to 10 weeks for backend API plus strong Next.js website foundation. Flutter starts afterward.

- Phase 0 - Reset and rules: 1 day
- Phase 1 - Backend foundation: 4 to 5 days
- Phase 2 - Auth and roles: 4 to 5 days
- Phase 3 - Catalog: 7 to 9 days
- Phase 4 - Cart and checkout: 7 to 9 days
- Phase 5 - Orders and payments: 8 to 10 days
- Phase 6 - Admin operations: 7 to 9 days
- Phase 7 - Next.js customer website: 12 to 15 days
- Phase 8 - QA, hardening, launch prep: 7 to 10 days

## Work Tracking Rule

After each meaningful development session, update `docs/DEVELOPMENT_TRACKER.md` with date, project touched, files/modules changed, completed work, testing, bugs, next task, and open decisions.

