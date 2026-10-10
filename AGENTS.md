# Dwarkesh Bakers Agent Rules

These rules apply to the Dwarkesh Bakers rebuild across the Laravel API, Next.js frontend, and later Flutter app.

## Product Direction

- Build a single-brand, single-bakery ordering platform for Dwarkesh Bakers.
- Do not build marketplace, multi-store, vendor onboarding, commission, payout, restaurant listing, or store comparison features.
- The Laravel API is the source of truth for both the Next.js website and the future Flutter app.
- Development priority is Laravel API first, Next.js website second, Flutter app later.

## Project Scope

Active projects:

- Backend API: `D:\laravel\dwarkesh-bakers`
- Website: `D:\laravel\dwarkesh-bakers-frontend`
- Flutter app: `D:\laravel\dwarkesh_bakers_flutter`

The Flutter project is paused for now. Do not modify it unless the user explicitly starts mobile development.

## Rebuild Rule

The existing developed code is not the target architecture. Treat it as disposable reference only. Rebuild cleanly from scratch in planned phases.

Before removing or replacing existing files, create or confirm a backup branch/tag. Never delete project files silently.

## Backend Standards

- Use Laravel best practices and Laravel 12 conventions.
- Keep controllers thin. Put business rules in services/actions/classes.
- Use Form Request classes for validation. Do not validate large request payloads inside controllers.
- Use traits only for cross-cutting behavior such as API responses, image URL helpers, or reusable model scopes.
- Use API Resources or explicit response transformer classes for frontend-facing output. Responses must include only the fields needed by the frontend.
- Keep response structure consistent across all endpoints.
- Use only `GET` and `POST` HTTP methods for routes. Do not use resource routes.
- Do not use `Route::resource`, `apiResource`, `PUT`, `PATCH`, or `DELETE` routes.
- Controller method names must describe the business action, for example `updateProductCategory()`, `markOrderAsPreparing()`, `cancelCustomerOrder()`.
- Every controller method and Form Request must include a short PHPDoc comment explaining purpose, expected use, and important parameters.
- Use database transactions for multi-table writes such as checkout, payment capture, refunds, inventory changes, and order status changes.
- Use enums or constants for fixed statuses: order status, payment status, fulfillment type, user role, coupon type, product availability.
- Do not expose internal IDs, audit fields, deleted flags, raw file paths, or unused database columns unless the frontend needs them.

## API Response Standard

Create and use `App\Traits\ApiResponseTrait` for every API controller.

Success shape:

```json
{
  "success": true,
  "message": "Readable success message.",
  "data": {},
  "meta": {}
}
```

Error shape:

```json
{
  "success": false,
  "message": "Readable error message.",
  "errors": {},
  "meta": {}
}
```

Validation error shape:

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

Rules:

- `success`, `message`, and either `data` or `errors` are always present.
- `meta` is present when pagination, filters, totals, or contextual flags are needed.
- Do not return full Eloquent models directly from controllers.
- Do not return database tables as raw arrays.

## Image Path Standard

Create `config/imagepath.php` and use it everywhere image paths are needed.

Required path groups:

- `business.logo`
- `business.cover`
- `category.image`
- `product.main_image`
- `product.gallery`
- `product.custom_uploads`
- `banner.image`
- `review.image`
- `payment.proof`
- `user.avatar`

Models with images must expose frontend-safe URL attributes, such as `image_url`, `logo_url`, `gallery_urls`, or `avatar_url`. Do not make the frontend build storage paths manually.

## Core Backend Structure

Expected folders:

- `app/Actions` for single-purpose write operations.
- `app/Services` for domain services and integrations.
- `app/Data` or `app/DTOs` for typed request-to-service data objects where useful.
- `app/Enums` for statuses and fixed types.
- `app/Http/Controllers/Api/V1/Customer` for customer API controllers.
- `app/Http/Controllers/Api/V1/Admin` for admin API controllers.
- `app/Http/Requests/Api/V1/...` for validation.
- `app/Http/Resources/Api/V1/...` for frontend responses.
- `app/Traits` for reusable Laravel traits.

## Route Standard

Use `routes/api.php` with explicit GET and POST routes only.

Good:

```php
Route::get('/v1/products/list', [ProductController::class, 'getProductList']);
Route::post('/v1/admin/products/update-category', [ProductCategoryController::class, 'updateProductCategory']);
```

Do not use:

```php
Route::apiResource('products', ProductController::class);
Route::put('/products/{product}', ...);
Route::patch('/products/{product}', ...);
Route::delete('/products/{product}', ...);
```

## Frontend Standards

- Build the customer website in Next.js first.
- Keep UI consistent across pages: shared layout, buttons, forms, product cards, dialogs, status messages, empty states, and error states.
- The website must consume the Laravel API response shape consistently.
- Do not duplicate backend business rules in the frontend. The frontend can validate user convenience fields, but backend remains authoritative.
- Keep API clients typed and centralized.
- Show only single-bakery flows: delivery or takeaway from Dwarkesh Bakers.
- Do not create marketplace screens, store browsing, vendor dashboards, or seller selection.

## Language Standard

Support Gujarati as default, plus English and Hindi.

- Store translatable display fields where needed.
- API responses should support locale-aware labels.
- Admin forms should allow managing translations for product/category/customer-facing content.

## Documentation Rule

After each meaningful development step, update:

- `docs/DEVELOPMENT_TRACKER.md`
- `docs/PROJECT_REBUILD_PLAN.md` if scope or timeline changes
- API documentation when routes, request parameters, or response payloads change

## Verification Rule

Before marking any phase complete:

- Run Laravel tests or targeted API checks for backend changes.
- Run Next.js build/lint or targeted UI checks for frontend changes.
- Manually verify important customer flows when UI changes.
- Record what was tested in `docs/DEVELOPMENT_TRACKER.md`.

