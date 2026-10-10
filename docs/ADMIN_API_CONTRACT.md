# Admin API contract and route registration

Admin endpoints use `/api` as Laravel's API prefix. Register only the explicit `GET` and `POST` paths below; do not use resource routes. All endpoints except login require `auth:sanctum` and the registered `admin.role` middleware. Owner/admin/staff are accepted for normal operations; staff provisioning should additionally use `admin.role:owner,admin`. Login applies a five-failed-attempts-per-minute limiter keyed by identifier and IP in the controller.

Responses use `ApiResponseTrait`'s envelope:

```json
{"success":true,"message":"...","data":{},"meta":{}}
```

## Auth and dashboard

| Verb | Path | Handler |
|---|---|---|
| POST | `/v1/admin/auth/login-admin` | `AdminAuthController@loginAdmin` |
| POST | `/v1/admin/auth/logout-admin` | `AdminAuthController@logoutAdmin` |
| GET | `/v1/admin/dashboard/get-dashboard-summary` | `AdminDashboardController@getDashboardSummary` |

Login body is `{ "identifier": "mobile-or-email", "password": "..." }`. `data` contains `token` and `admin: {name, role, avatar_url}`. Login accepts active owner/admin/staff accounts only. Logout deletes the current Sanctum token. Regular staff are limited by middleware to order and support operations; they cannot mutate catalog/business settings, coupons, customers or staff accounts.

## Settings and catalog

| Verb | Path | Handler |
|---|---|---|
| GET / POST | `/v1/admin/settings/get-business-settings` / `/v1/admin/settings/update-business-settings` | `AdminBusinessController@getBusinessSettings` / `updateBusinessSettings` |
| GET | `/v1/admin/categories/get-category-list` | `AdminCatalogController@getCategoryList` |
| POST | `/v1/admin/categories/create-category` / `update-category` | `AdminCatalogController@createCategory` / `updateCategory` |
| POST | `/v1/admin/categories/update-category-status` / `delete-category` | `AdminCatalogController@updateCategoryStatus` / `deleteCategory` |
| GET | `/v1/admin/products/get-product-list` | `AdminCatalogController@getProductList` |
| POST | `/v1/admin/products/create-product` / `update-product` | `AdminCatalogController@createProduct` / `updateProduct` |
| POST | `/v1/admin/products/update-product-category` / `update-product-status` | `AdminCatalogController@updateProductCategory` / `updateProductStatus` |
| POST | `/v1/admin/products/delete-product` / `upload-product-image` / `delete-product-image` / `update-product-stock` | `AdminCatalogController` matching action names |
| GET | `/v1/admin/products/get-flavour-list` / `get-addon-list` | `AdminCatalogController@getFlavourList` / `getAddonList` |
| POST | `/v1/admin/products/save-flavour` / `save-addon` | `AdminCatalogController@saveFlavour` / `saveAddon` |

Category payloads use `name_translations`, optional `description_translations`, `slug`, `parent_id`, `is_active`, `is_featured`, `sort_order`, and multipart `image`/`banner_image`. Product payloads use `product_category_id`, localized name/description maps, product type/food type, pricing, flags, optional multipart `main_image`, `variants[]`, `flavour_ids[]`, `addons[]` (`id`, `is_required`, `max_quantity`), `inventory`, and `customization`. Stock accepts `product_id`, optional `product_variant_id`, `stock_quantity`, `low_stock_threshold`, `track_stock`, `is_sold_out`, and `sold_out_until`. Image upload accepts `product_id`, multipart `image`, optional `alt_text_translations`, and `is_primary`. Catalog list responses include IDs, translated display values, image URLs, availability and relevant pricing/stock fields only.

Variant, product-specific flavour/add-on associations, inventory, and customization configuration are submitted as part of product save. Global flavour writes accept `{id?, name_translations, description_translations?, extra_price?, is_active?, sort_order?}`. Global add-on writes accept `{id?, name_translations, description_translations?, price, is_active?, sort_order?, image?}`. The current admin UI does not yet expose global flavour/add-on screens or editable delivery-zone/holiday/slot screens; those APIs are available for the parallel UI work.

## Orders and coupons

| Verb | Path | Handler |
|---|---|---|
| GET | `/v1/admin/orders/get-order-list` / `get-order-detail` | `AdminOrderController@getOrderList` / `getOrderDetail` |
| POST | `/v1/admin/orders/accept-order` / `mark-order-as-preparing` / `mark-order-as-ready` / `mark-order-as-out-for-delivery` / `mark-order-as-delivered` / `mark-order-as-picked-up` / `cancel-order` | `AdminOrderController` matching named action |
| GET | `/v1/admin/coupons/get-coupon-list` | `AdminOperationsController@getCouponList` |
| POST | `/v1/admin/coupons/create-coupon` / `update-coupon` / `update-coupon-status` / `delete-coupon` | `AdminOperationsController` matching action |

Order detail and transitions use `{ "id": 123 }`; cancellation can include `reason`. The response exposes order ID/number/status, customer name/mobile, delivery or pickup fulfillment details, requested times, totals, items and status timeline. The state graph rejects invalid transitions and checks that dispatch/delivery applies to delivery and collection applies to takeaway. Coupon writes use `{id?, code, title_translations, description_translations?, discount_type, discount_value, max_discount_amount?, minimum_order_amount?, starts_at?, ends_at?, usage_limit?, usage_limit_per_customer?, applies_to_fulfillment_type?, is_active}`.

## Delivery, support, staff and audit

| Verb | Path | Handler |
|---|---|---|
| GET | `/v1/admin/delivery/get-zone-list` | `AdminBusinessController@getDeliveryZones` |
| POST | `/v1/admin/delivery/create-zone` / `update-zone` / `update-zone-status` / `delete-zone` | `AdminBusinessController` matching action |
| GET / POST | `/v1/admin/delivery/get-opening-hours` / `update-opening-hours` | `AdminBusinessController@getOpeningHours` / `updateOpeningHours` |
| GET / POST | `/v1/admin/delivery/get-holiday-list` / `create-holiday` / `delete-holiday` | `AdminBusinessController@getHolidayList` / matching write action |
| GET / POST | `/v1/admin/delivery/get-delivery-slots` / `save-delivery-slot` | `AdminBusinessController@getDeliverySlots` / `saveDeliverySlot` |
| GET | `/v1/admin/support/get-ticket-list` / `get-ticket-detail` | `AdminOperationsController@getSupportTicketList` / `getSupportTicketDetail` |
| POST | `/v1/admin/support/reply-ticket` / `update-ticket-status` | `AdminOperationsController@replyTicket` / `updateTicketStatus` |
| GET | `/v1/admin/staff/get-staff-list` | `AdminOperationsController@getStaffList` |
| POST | `/v1/admin/staff/create-staff` / `update-staff` / `update-staff-status` | `AdminOperationsController` matching action |
| GET | `/v1/admin/staff/get-activity-log` | `AdminOperationsController@getActivityLog` |

### Dispatcher, distance pricing, and live support

| Verb | Path | Handler |
|---|---|---|
| GET | `/v1/admin/orders/get-dispatcher-orders?status={status}` | `AdminOrderController@getDispatcherOrders` |
| GET | `/v1/admin/orders/export-order-list?format=csv\|pdf` | `AdminOrderController@exportOrderList` |
| GET | `/v1/admin/delivery/get-fee-range-list` | `AdminDeliveryPricingController@getDeliveryFeeRanges` |
| POST | `/v1/admin/delivery/create-fee-range` / `update-fee-range` / `update-fee-range-status` / `delete-fee-range` | `AdminDeliveryPricingController` matching action |
| POST | `/v1/location/check-delivery-availability` | `CustomerDeliveryPricingController@checkDeliveryAvailability` |
| GET | `/v1/support/get-ticket-list` / `get-ticket-detail` / `get-attachment` | `CustomerSupportController` matching action |
| POST | `/v1/support/create-ticket` / `reply-ticket` / `mark-messages-read` / `send-typing-indicator` | `CustomerSupportController` matching action |
| GET | `/v1/admin/support/get-ticket-list` / `get-ticket-detail` / `get-attachment` | `AdminSupportController` matching action |
| POST | `/v1/admin/support/reply-ticket` / `mark-messages-read` / `send-typing-indicator` / `update-ticket-status` | `AdminSupportController` matching action |

Dispatcher status queues group the underlying order states into customer-friendly tabs; status mutations remain explicit POST actions and broadcast changes to authorized admin and customer order channels. Delivery quotes calculate distance from the configured store pin, enforce the maximum distance, and select an active fee range. Support attachments are served through authenticated ownership-checked endpoints. Support updates use private Reverb channels `admin.support` and `support.{ticketNumber}`.

Zone writes accept name, areas/pincodes arrays, optional center/radius/minimum/free-delivery values, fee, estimated minutes and active/sort fields. Opening hours accept exactly seven rows with unique `day_of_week` (0–6), `opens_at`, `closes_at`, and `is_closed`. Holiday writes accept date, title, closed/open fields and note. Slot writes accept delivery/takeaway, label, start/end, max orders, active and sort order. Support routes accept the current UI's `ticket_number` or a numeric `id`; reply body includes `message`, and status writes include `status`. Staff creation requires name, mobile, role (`admin|staff|delivery`) and a password of at least 12 characters; updates may omit password. `admin.role:owner,admin` is required for staff writes.

## Initial owner bootstrap

There is intentionally no public owner-registration endpoint. After migrations and application secrets are configured, run `php artisan admin:create-owner` once from a trusted deployment shell. The command prompts for name, mobile, optional email, and a confirmed password of at least 12 characters; it refuses duplicate owner accounts or already-used contact details and stores a hashed password. Demo fixtures—including the former `password` seed credentials—are skipped when `APP_ENV=production`.

## Route registration shape

Import the admin controllers and register explicit actions from the tables above. Login stays outside the protected group:

```php
Route::post('/v1/admin/auth/login-admin', [AdminAuthController::class, 'loginAdmin']); // Controller rate-limits failed credentials.
Route::middleware(['auth:sanctum', 'admin.role'])->prefix('/v1/admin')->group(function () {
    // Register each GET/POST path above with its listed controller action.
});
Route::middleware(['auth:sanctum', 'admin.role:owner,admin'])->prefix('/v1/admin')->group(function () {
    // Register POST create-staff, update-staff, and update-staff-status here.
});
```
