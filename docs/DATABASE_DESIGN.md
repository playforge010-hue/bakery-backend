# Dwarkesh Bakers Database Design

Status: Draft ready for implementation
Last updated: 2026-10-04
Source note: `C:\Users\hp\Desktop\That's looks greate bu keep one thi.txt`

## Core Decision

Dwarkesh Bakers is a single-brand, single-bakery ordering platform. The database must not be designed like Swiggy, Zomato, or a multi-vendor marketplace.

Customers order only from Dwarkesh Bakers. Delivery and takeaway are supported, but customers never choose between stores or sellers.

## Strictly Not Allowed

Do not create these concepts or tables:

- stores
- store_id on core business tables
- vendors or merchants
- vendor onboarding
- store selection
- restaurant/store ratings
- marketplace commission
- vendor payout
- merchant settlement
- store comparison
- multi-store carts
- seller dashboards
- branch selection for takeaway unless the business later explicitly asks for multiple branches

If a future feature sounds like it needs `store_id`, stop and redesign it for one bakery first.

## High-Level Model

```text
Dwarkesh Bakers
|-- Business settings
|-- Opening hours
|-- Delivery zones
|-- Delivery slots
|-- Categories
|   |-- Products
|       |-- Images
|       |-- Variants / weights
|       |-- Flavours
|       |-- Egg / eggless options
|       |-- Add-ons
|       |-- Cake customization
|       |-- Inventory
|-- Customers
|   |-- OTP authentication
|   |-- Saved addresses
|   |-- Favorites
|   |-- Reviews
|   |-- Notifications
|-- Cart
|-- Orders
|   |-- Delivery orders
|   |-- Takeaway orders
|-- Payments
|-- Coupons / offers
|-- Delivery tracking
|-- Refunds
|-- Support
|-- Admin panel
```

## Naming Standards

- Use plural snake_case table names.
- Use `id` as primary key.
- Use foreign keys where relationships are required.
- Use `is_active`, `sort_order`, `created_at`, `updated_at` consistently.
- Use `deleted_at` only where soft delete is useful for business data.
- Use enums/constants in PHP for fixed values.
- Do not expose raw database rows to frontend responses.

## Languages

Supported languages:

- Gujarati: default
- English
- Hindi

Preferred implementation:

- Store translatable fields in JSON columns where the field is simple and belongs to one record, for example `name_translations`, `description_translations`.
- JSON keys: `gu`, `en`, `hi`.
- API resources return the requested locale value plus fallback.

Example:

```json
{
  "name_translations": {
    "gu": "ચોકલેટ કેક",
    "en": "Chocolate Cake",
    "hi": "चॉकलेट केक"
  }
}
```

## Foundation Tables

### users

Purpose: Admin, staff, delivery persons, and optionally customers if one user table is used.

Important columns:

- id
- name
- mobile_number
- email nullable
- password nullable for OTP-only customers
- role: owner, order_staff, kitchen_staff, delivery_staff, support_staff, customer
- avatar_path nullable
- is_active
- last_login_at nullable
- remember_token
- timestamps
- soft deletes optional

Notes:

- Admin/staff use password login.
- Customers use OTP login.
- If customer data grows complex, keep customer profile details in `customer_profiles`.

### customer_profiles

Purpose: Customer-specific profile details when users table is shared.

Important columns:

- id
- user_id
- full_name nullable
- mobile_number
- email nullable
- date_of_birth nullable
- anniversary_date nullable
- preferred_language default gu
- marketing_opt_in default false
- timestamps

### business_settings

Purpose: Editable configuration for Dwarkesh Bakers.

Important columns:

- id
- bakery_name
- logo_path nullable
- cover_image_path nullable
- address_line_1
- address_line_2 nullable
- area
- city default Jamnagar
- state default Gujarat
- pincode
- latitude nullable
- longitude nullable
- phone_number
- whatsapp_number
- email nullable
- gst_number nullable
- fssai_number nullable
- minimum_order_amount
- default_preparation_minutes
- delivery_available default true
- takeaway_available default true
- online_payment_available default true
- cod_available default false
- upi_available default true
- maintenance_mode default false
- maintenance_message nullable
- social_links_json nullable
- timestamps

Notes:

- There should usually be one row.
- This replaces all multi-store settings.

### opening_hours

Purpose: Normal weekly opening and closing hours.

Important columns:

- id
- day_of_week integer 0-6
- opens_at nullable
- closes_at nullable
- is_closed default false
- timestamps

### business_holidays

Purpose: Special closed days or changed timing.

Important columns:

- id
- holiday_date
- title
- opens_at nullable
- closes_at nullable
- is_closed default true
- note nullable
- timestamps

### delivery_zones

Purpose: Define Jamnagar delivery areas, pincodes, radius, and fees.

Important columns:

- id
- name
- area_names_json nullable
- pincodes_json nullable
- center_latitude nullable
- center_longitude nullable
- radius_km nullable
- minimum_order_amount nullable
- delivery_fee
- free_delivery_above nullable
- estimated_delivery_minutes
- is_active
- sort_order
- timestamps

Notes:

- Attached directly to Dwarkesh Bakers, not to a store.
- Can support area/pincode/radius style matching.

### delivery_slots

Purpose: Customer-selectable delivery or pickup time windows.

Important columns:

- id
- fulfillment_type: delivery, takeaway, both
- label
- starts_at
- ends_at
- max_orders nullable
- is_active
- sort_order
- timestamps

## Catalog Tables

### product_categories

Purpose: Product grouping such as Cakes, Pastries, Cookies, Snacks.

Important columns:

- id
- parent_id nullable
- name_translations json
- slug
- description_translations nullable json
- image_path nullable
- banner_image_path nullable
- is_featured default false
- is_active default true
- sort_order
- timestamps
- soft deletes

### products

Purpose: Sellable bakery items.

Important columns:

- id
- product_category_id
- name_translations json
- slug
- short_description_translations nullable json
- description_translations nullable json
- main_image_path nullable
- base_price
- price_starts_from default true
- sku nullable
- product_type: cake, pastry, snack, bread, cookie, beverage, other
- food_type: veg, egg, eggless, both
- is_eggless_available default false
- is_egg_available default false
- is_customizable default false
- allow_message default false
- allow_photo_upload default false
- min_preparation_minutes
- max_preparation_minutes nullable
- is_featured default false
- is_bestseller default false
- is_new default false
- is_available default true
- sold_out_until nullable
- sort_order
- seo_title nullable
- seo_description nullable
- timestamps
- soft deletes

Notes:

- Do not store cost price in customer API responses.
- Product availability must support sold-out states.

### product_images

Purpose: Gallery images for products.

Important columns:

- id
- product_id
- image_path
- alt_text_translations nullable json
- is_primary default false
- sort_order
- timestamps

### product_variants

Purpose: Sellable variations such as 500g, 1kg, 2kg cake.

Important columns:

- id
- product_id
- name_translations json
- sku nullable
- weight_value nullable decimal
- weight_unit nullable: g, kg, piece
- price
- compare_at_price nullable
- min_preparation_minutes nullable
- is_default default false
- is_active default true
- sort_order
- timestamps

### product_flavours

Purpose: Available flavours for cakes and selected products.

Important columns:

- id
- name_translations json
- description_translations nullable json
- extra_price default 0
- is_active default true
- sort_order
- timestamps

### product_flavour_options

Purpose: Product-to-flavour mapping.

Important columns:

- id
- product_id
- product_flavour_id
- is_default default false
- timestamps

### product_addons

Purpose: Add-ons such as candles, knife, toppers, extra chocolate, greeting card.

Important columns:

- id
- name_translations json
- description_translations nullable json
- image_path nullable
- price
- is_active default true
- sort_order
- timestamps

### product_addon_options

Purpose: Product-to-add-on mapping.

Important columns:

- id
- product_id
- product_addon_id
- is_required default false
- max_quantity nullable
- timestamps

### product_customization_options

Purpose: Defines customization rules per product.

Important columns:

- id
- product_id
- allow_message default false
- message_label_translations nullable json
- message_max_length nullable
- allow_photo_upload default false
- photo_upload_label_translations nullable json
- allow_flavour_selection default false
- allow_egg_selection default false
- allow_delivery_date_selection default true
- min_notice_minutes nullable
- max_schedule_days nullable
- timestamps

### product_inventory

Purpose: Stock and sold-out control.

Important columns:

- id
- product_id
- product_variant_id nullable
- track_stock default false
- stock_quantity nullable
- low_stock_threshold nullable
- is_sold_out default false
- sold_out_until nullable
- timestamps

## Customer Tables

### customer_otps

Purpose: OTP login for customers.

Important columns:

- id
- mobile_number
- otp_hash
- purpose: login, register
- expires_at
- verified_at nullable
- attempts default 0
- ip_address nullable
- timestamps

### customer_addresses

Purpose: Saved delivery addresses.

Important columns:

- id
- user_id
- label nullable
- receiver_name
- receiver_mobile_number
- address_line_1
- address_line_2 nullable
- landmark nullable
- area
- city default Jamnagar
- state default Gujarat
- pincode
- latitude nullable
- longitude nullable
- delivery_zone_id nullable
- is_default default false
- timestamps
- soft deletes

### customer_favorites

Purpose: Favorite products.

Important columns:

- id
- user_id
- product_id
- timestamps

Unique rule:

- user_id + product_id must be unique.

### customer_notifications

Purpose: Customer notification inbox.

Important columns:

- id
- user_id
- title
- body
- type
- data_json nullable
- read_at nullable
- timestamps

## Cart Tables

### carts

Purpose: Active customer or guest cart.

Important columns:

- id
- user_id nullable
- guest_token nullable
- fulfillment_type nullable: delivery, takeaway
- delivery_zone_id nullable
- customer_address_id nullable
- scheduled_for nullable
- coupon_id nullable
- currency default INR
- subtotal
- addon_total
- discount_total
- delivery_fee
- tax_total
- grand_total
- expires_at nullable
- timestamps

### cart_items

Purpose: Products selected in cart.

Important columns:

- id
- cart_id
- product_id
- product_variant_id nullable
- quantity
- unit_price
- addon_total
- line_total
- selected_flavour_id nullable
- egg_option nullable: egg, eggless
- cake_message nullable
- customization_summary_json nullable
- timestamps

### cart_item_addons

Purpose: Add-ons selected for a cart item.

Important columns:

- id
- cart_item_id
- product_addon_id
- quantity
- unit_price
- line_total
- timestamps

### cart_item_uploads

Purpose: Customer-uploaded customization images before order placement.

Important columns:

- id
- cart_item_id
- file_path
- original_name nullable
- mime_type
- file_size
- timestamps

## Order Tables

### orders

Purpose: Confirmed customer orders.

Important columns:

- id
- order_number
- user_id nullable
- customer_name
- customer_mobile_number
- customer_email nullable
- fulfillment_type: delivery, takeaway
- order_status: pending, confirmed, preparing, ready, out_for_delivery, delivered, picked_up, cancelled, refunded
- payment_status: pending, paid, failed, refunded, partially_refunded
- payment_method: upi, gateway, cod, cash, manual
- delivery_zone_id nullable
- delivery_address_json nullable
- pickup_address_json nullable
- scheduled_for nullable
- placed_at
- accepted_at nullable
- prepared_at nullable
- ready_at nullable
- out_for_delivery_at nullable
- delivered_at nullable
- picked_up_at nullable
- cancelled_at nullable
- cancellation_reason nullable
- subtotal
- addon_total
- discount_total
- delivery_fee
- tax_total
- grand_total
- customer_note nullable
- internal_note nullable
- coupon_id nullable
- coupon_code nullable
- timestamps
- soft deletes optional

Notes:

- Store address snapshots as JSON so old orders remain correct if customer edits address later.
- Takeaway orders use bakery pickup address, not store selection.

### order_items

Purpose: Product snapshots inside an order.

Important columns:

- id
- order_id
- product_id nullable
- product_variant_id nullable
- product_name
- variant_name nullable
- sku nullable
- quantity
- unit_price
- addon_total
- line_total
- selected_flavour_name nullable
- egg_option nullable
- cake_message nullable
- customization_summary_json nullable
- timestamps

### order_item_addons

Purpose: Add-on snapshots inside an order item.

Important columns:

- id
- order_item_id
- product_addon_id nullable
- addon_name
- quantity
- unit_price
- line_total
- timestamps

### order_item_uploads

Purpose: Uploaded images attached to ordered customized cakes.

Important columns:

- id
- order_item_id
- file_path
- original_name nullable
- mime_type
- file_size
- timestamps

### order_status_histories

Purpose: Timeline for tracking orders.

Important columns:

- id
- order_id
- old_status nullable
- new_status
- changed_by_user_id nullable
- note nullable
- visible_to_customer default true
- timestamps

### delivery_assignments

Purpose: Assign own delivery persons if used.

Important columns:

- id
- order_id
- delivery_user_id
- assigned_at
- picked_up_at nullable
- delivered_at nullable
- status: assigned, picked_up, delivered, failed, cancelled
- note nullable
- timestamps

### delivery_tracking_events

Purpose: Delivery tracking updates.

Important columns:

- id
- order_id
- delivery_assignment_id nullable
- status
- latitude nullable
- longitude nullable
- note nullable
- visible_to_customer default true
- timestamps

## Payment And Offer Tables

### coupons

Purpose: Coupon and offer management.

Important columns:

- id
- code
- title_translations json
- description_translations nullable json
- discount_type: percentage, fixed
- discount_value
- max_discount_amount nullable
- minimum_order_amount nullable
- starts_at nullable
- ends_at nullable
- usage_limit nullable
- usage_limit_per_customer nullable
- applies_to_fulfillment_type nullable: delivery, takeaway, both
- is_active default true
- timestamps
- soft deletes

### coupon_redemptions

Purpose: Track coupon usage.

Important columns:

- id
- coupon_id
- user_id nullable
- order_id
- code
- discount_amount
- timestamps

### payments

Purpose: Payment records for orders.

Important columns:

- id
- order_id
- provider: razorpay, phonepe, cashfree, manual_upi, cod, cash
- provider_order_id nullable
- provider_payment_id nullable
- provider_signature nullable
- amount
- currency default INR
- status: pending, authorized, captured, failed, refunded, partially_refunded
- paid_at nullable
- failure_reason nullable
- raw_response_json nullable
- timestamps

### payment_webhook_logs

Purpose: Store payment provider webhook payloads for debugging and audit.

Important columns:

- id
- provider
- event_type nullable
- provider_payment_id nullable
- payload_json
- processed_at nullable
- processing_error nullable
- timestamps

### refunds

Purpose: Refunds for cancelled or adjusted orders.

Important columns:

- id
- order_id
- payment_id nullable
- amount
- reason
- status: requested, approved, processing, completed, failed, rejected
- provider_refund_id nullable
- requested_by_user_id nullable
- processed_by_user_id nullable
- processed_at nullable
- raw_response_json nullable
- timestamps

## Review And Support Tables

### product_reviews

Purpose: Product/order reviews.

Important columns:

- id
- order_id
- order_item_id nullable
- product_id nullable
- user_id
- rating integer 1-5
- title nullable
- comment nullable
- image_path nullable
- is_approved default false
- approved_by_user_id nullable
- approved_at nullable
- timestamps

### support_tickets

Purpose: Customer support requests.

Important columns:

- id
- ticket_number
- user_id nullable
- order_id nullable
- subject
- status: open, in_progress, resolved, closed
- priority: low, normal, high, urgent
- assigned_to_user_id nullable
- timestamps

### support_messages

Purpose: Messages inside a support ticket.

Important columns:

- id
- support_ticket_id
- sender_user_id nullable
- sender_type: customer, staff, system
- message
- attachment_path nullable
- timestamps

## Admin And Audit Tables

### admin_activity_logs

Purpose: Track important admin/staff actions.

Important columns:

- id
- user_id nullable
- action
- subject_type nullable
- subject_id nullable
- properties_json nullable
- ip_address nullable
- user_agent nullable
- timestamps

### banners

Purpose: Website/app banners for home and offers.

Important columns:

- id
- title_translations json
- subtitle_translations nullable json
- image_path
- link_type nullable
- link_value nullable
- starts_at nullable
- ends_at nullable
- is_active default true
- sort_order
- timestamps

## Image Path Mapping

Use `config/imagepath.php` for all image folders:

- business.logo: `business/logo`
- business.cover: `business/cover`
- category.image: `categories`
- product.main_image: `products/main`
- product.gallery: `products/gallery`
- product.custom_uploads: `orders/custom-uploads`
- banner.image: `banners`
- review.image: `reviews`
- payment.proof: `payments/proofs`
- user.avatar: `users/avatars`
- support.attachment: `support/attachments`

Models/resources must return public URL attributes. The frontend must never assemble storage paths.

## Main Relationships

- User has one customer profile.
- User has many customer addresses.
- User has many customer favorites.
- Product category has many products.
- Product has many images, variants, flavour options, add-on options, customization options, and inventory records.
- Cart has many cart items.
- Cart item has many add-ons and uploads.
- Order has many order items, status histories, payments, refunds, support tickets, and delivery tracking events.
- Order item has many add-ons and uploads.
- Coupon has many redemptions.

## Fulfillment Rules

Delivery:

1. Customer selects or detects location.
2. API checks delivery zone by area, pincode, or radius.
3. API calculates delivery fee and estimated delivery time.
4. Customer selects now or scheduled delivery.
5. Order stores a delivery address snapshot.

Takeaway:

1. Customer chooses takeaway.
2. API returns Dwarkesh Bakers pickup address from business settings.
3. Customer selects pickup date/time.
4. Order stores pickup address snapshot.
5. No store or branch selection is shown.

## Customer Flow Coverage

The database supports:

- Location detection and delivery availability
- Bakery home data
- Browse categories and products
- Search products
- Product customization
- Cake message
- Photo upload
- Cart
- Delivery or takeaway checkout
- OTP login
- Saved addresses
- Scheduled orders
- Coupons
- UPI/payment gateway/manual payment readiness
- Order confirmation
- Order tracking
- Delivered or picked up completion
- Review
- Reorder
- Notifications
- Support

## Migration Build Order

1. users and customer_profiles
2. business_settings, opening_hours, business_holidays
3. delivery_zones, delivery_slots
4. product_categories
5. products
6. product_images
7. product_variants
8. product_flavours and product_flavour_options
9. product_addons and product_addon_options
10. product_customization_options
11. product_inventory
12. customer_otps, customer_addresses, customer_favorites, customer_notifications
13. carts, cart_items, cart_item_addons, cart_item_uploads
14. coupons and coupon_redemptions
15. orders
16. order_items, order_item_addons, order_item_uploads
17. order_status_histories
18. delivery_assignments and delivery_tracking_events
19. payments, payment_webhook_logs, refunds
20. product_reviews
21. support_tickets and support_messages
22. admin_activity_logs
23. banners

## Open Decisions Before Migration Coding

- Payment provider: Razorpay, PhonePe, Cashfree, manual UPI, COD, or mixed.
- OTP provider: SMS, WhatsApp, Firebase, MSG91, Twilio, or local manual testing first.
- Whether customers and staff share `users` table or customers get a separate `customers` table. Current recommendation: shared `users` table with roles plus `customer_profiles`.
- Tax handling: simple tax fields now or full GST invoice breakdown.
- Delivery zone matching priority: pincode first, area first, radius first, or combined.
