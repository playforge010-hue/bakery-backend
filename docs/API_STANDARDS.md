# API Standards

## Route Rules

- Use only `GET` and `POST`.
- Do not use `PUT`, `PATCH`, or `DELETE`.
- Do not use `Route::resource()` or `Route::apiResource()`.
- Use explicit action names in route paths and controller method names.

Examples:

```php
Route::get('/v1/products/get-product-list', [ProductController::class, 'getProductList']);
Route::post('/v1/products/update-product-category', [ProductController::class, 'updateProductCategory']);
```

## Controller Rules

Each controller method must:

- Have a PHPDoc comment.
- Use a Form Request for validation when input is more than a simple ID/filter.
- Call an action/service for business logic.
- Return response through `ApiResponseTrait`.
- Return resources/transformers, not raw Eloquent models.

## Response Rules

Success:

```json
{
  "success": true,
  "message": "Product list loaded successfully.",
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 3
  }
}
```

Error:

```json
{
  "success": false,
  "message": "Product is not available.",
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
    "mobile_number": ["The mobile number field is required."]
  },
  "meta": {}
}
```

## Frontend Field Rule

Design every payload for its screen. Customer product cards, cart rows, order cards, admin tables, and detail pages must each get the exact fields needed for that UI.

Never expose raw storage paths, passwords, tokens, cost price, internal notes, audit-only fields, deleted flags, or full relational tables when a summary is enough.

## Locale

Send `X-Locale: gu`, `X-Locale: en`, or `X-Locale: hi` to select Gujarati, English, or Hindi display labels. Gujarati is the default when the header is missing or unsupported.

## Authentication and token abilities

- Customer OTP verification issues a Sanctum token with `customer-access`. Protected customer routes require `auth:sanctum`, that ability, and an active customer role.
- Admin login issues `admin-access`, `admin:read`, and `admin:write`. Admin routes require `admin-access`; admin middleware additionally requires `admin:read` for GET and `admin:write` for POST, then applies the route's role restrictions.
- Logout revokes the current token. Customer and admin broadcast channels enforce the corresponding ability and ownership/role checks.
- Tokens created before this ability contract may need to sign in again to receive the new scopes.

## Customer checkout and ordering contract

Wire these explicit routes to `App\Http\Controllers\Api\V1\Customer\CustomerCheckoutController` (all except product options require `auth:sanctum`):

| Method | Path | Action |
| --- | --- | --- |
| GET | `/v1/products/get-product-order-options?slug={slug}` | `getProductOrderOptions` |
| POST | `/v1/cart/add-configured-product` | `addConfiguredProductToCart` |
| GET | `/v1/checkout/get-available-schedules?fulfillment_type=delivery\|takeaway&days=10` | `getAvailableSchedules` |
| POST | `/v1/checkout/get-checkout-summary` | `getCheckoutSummary` |
| POST | `/v1/orders/place-customer-order` | `placeCustomerOrder` |

`add-configured-product` accepts `multipart/form-data`: required `product_id`, `quantity`; optional `product_variant_id`, `flavour_id`, `egg_option`, `cake_message`, `fulfillment_type`, `custom_image`; and indexed `addons[0][id]`, `addons[0][quantity]` pairs. Required add-ons must be selected. Prices are never accepted from the client. `custom_image` accepts JPG/JPEG/PNG/WebP up to 5 MB and is stored under `config('imagepath.directories.product_custom_uploads')`.

`get-checkout-summary` accepts JSON `{ fulfillment_type, customer_address_id? }`; for delivery, send paired numeric `delivery_latitude` and `delivery_longitude` in valid geographic ranges. It returns item lines, subtotal, add-on total, current eligible coupon discount, distance-based delivery fee, tax (zero until a tax rate is configured), grand total, and distance eligibility. Delivery addresses are checked against the signed-in customer's saved-address ownership and configured maximum distance.

`get-available-schedules` returns slots as `{ value, date, label, slot, remaining_capacity }`. Slots must fit business opening hours or holiday hours, honor product prep/notice and schedule horizon settings, and have remaining configured capacity.

`place-customer-order` accepts JSON `{ fulfillment_type, payment_method, scheduled_for, customer_note?, customer_address_id?, delivery_address_label, delivery_address_text, delivery_latitude, delivery_longitude }`. Delivery requires a readable address and paired coordinates; `customer_address_id` is optional for a map-only destination and, when supplied, must belong to the signed-in customer. The server reprices and validates the cart again, checks schedule capacity and stock inside a transaction, snapshots delivery/pickup and item/add-on/upload data, decrements tracked stock, redeems eligible coupons, records pending payment/status history, and clears the cart. The result contains the order number, current order/payment status, authoritative total, scheduled time, and tracking URL.

## Existing customer address, cart, and order routes

The customer address, cart, and order routes use endpoint-specific Form Requests and return validation failures in the shared `success/message/errors/meta` envelope with HTTP 422.

- Address creation requires recipient name/mobile, address line 1 (or the existing `address` alias), area, and pincode. Coordinates must be supplied together and stay within geographic ranges. Updates require `address_id` and accept partial changes; the server checks address ownership.
- Cart quantity updates require `cart_item_id` and a quantity from 1 through 99. Removal requires `cart_item_id`; ownership is checked by the server.
- `POST /v1/coupons/apply-coupon` requires `coupon_code`; it checks dates, order threshold, fulfillment, usage limits, and discount caps against the signed-in customer's cart, then persists the eligible coupon. Checkout reprices and validates it again. A cart change clears the applied coupon so stale discounts cannot remain visible.
- `POST /v1/orders/place-order` remains as a compatibility alias for `POST /v1/orders/place-customer-order`. Both routes now use the same Form Request, server-side repricing, availability, stock, schedule, payment-method, coupon checks, transaction, and response contract.
- Order cancellation requires `order_number`; its optional `reason` is limited to 500 characters. Reorder requires an owned `order_number`.

## Distance-based delivery pricing

The bakery location is selected with the map control in Business Settings. `max_delivery_distance_km` is enforced from that point, and active fee ranges determine the customer delivery charge. Admins manage ranges through `GET /v1/admin/delivery/get-fee-range-list` and explicit POST create/update/status/delete actions. The customer quote endpoint is `POST /v1/location/check-delivery-availability`; it accepts destination `latitude` and `longitude` and returns distance, availability, and the applicable charge using the standard API envelope. Delivery orders outside the configured maximum distance are rejected again during checkout. Legacy area and pincode zone matching is no longer used by customer delivery availability.

Online payment methods are intentionally unavailable until a payment gateway can confirm captured payments. Cash on delivery/pickup is the only checkout method exposed when enabled by the bakery. The current order flow must not treat a pending online payment as paid.

## Live support chat

Customer support uses authenticated GET/POST endpoints under `/v1/support`; admin support uses `/v1/admin/support`. Customer ticket ownership and admin roles are checked for every ticket and attachment request. Messages support text plus multiple image attachments, read state, ticket status, and typing indicators. Reverb private channels are `support.{ticketNumber}` and `admin.support`; WebSocket delivery supplements persisted message reads so clients can recover after reconnecting. Attachment access is authorized through the API rather than exposing private storage paths.
