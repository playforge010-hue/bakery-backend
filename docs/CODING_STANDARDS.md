# Dwarkesh Bakers Coding Standards

This guide defines how we build the Dwarkesh Bakers Laravel API. It borrows the useful structure and layer boundaries from the TeacherWay guide, while keeping Dwarkesh's product, clients, routes, languages, response contract, and image configuration authoritative.

When an example conflicts with the rules in the root `AGENTS.md`, follow `AGENTS.md`. Update this guide when the architecture changes.

## 1. Product and architecture

Dwarkesh Bakers is one bakery with one catalog, one cart per customer, and delivery or takeaway fulfillment. This is not a marketplace. Do not add store selection, vendor onboarding, seller dashboards, commissions, payouts, restaurant listings, or multi-store comparisons.

Laravel is the source of truth for business rules and data. The API serves the Next.js website and, later, the Flutter app. Work on the Laravel API first, the website second, and Flutter only after the user resumes mobile development.

Use Laravel 12 conventions, one application and one database. Prefer Laravel's container, validation, authorization, Eloquent, queues, events, and transaction facilities over custom infrastructure.

## 2. Request lifecycle

```text
HTTP request
  -> routes/api.php (explicit GET or POST route and middleware)
  -> Form Request (authorization and input validation)
  -> thin API controller (coordinates the operation)
  -> Action for one write operation, or Service for reusable domain work/integration
  -> model/query layer and database transaction where needed
  -> API Resource or explicit response transformer
  -> ApiResponseTrait envelope
```

Controllers do not own pricing, order state transitions, stock rules, coupon eligibility, image storage rules, or other domain decisions. Keep orchestration in controllers and reusable or multi-step work in actions/services.

## 3. Project structure

Use these locations as the codebase grows:

| Concern | Location |
|---|---|
| Single-purpose writes | `app/Actions/Customer`, `app/Actions/Admin` |
| Domain logic and integrations | `app/Services/Customer`, `app/Services/Admin` |
| Statuses and fixed values | `app/Enums` |
| Typed operation data, when useful | `app/Data` or `app/DTOs` |
| API controllers | `app/Http/Controllers/Api/V1/Customer`, `.../Admin` |
| Validation and authorization | `app/Http/Requests/Api/V1/Customer`, `.../Admin` |
| API output shaping | `app/Http/Resources/Api/V1/Customer`, `.../Admin` |
| Shared response and image behavior | `app/Traits` |
| Image destinations | `config/imagepath.php` |
| Explicit API routes | `routes/api.php` |

Do not create generic `Helpers`, `Utils`, or catch-all classes. Name classes after the bakery operation or domain they serve.

## 4. Naming and method design

- Use singular model names and Laravel's conventional plural table names.
- Name controllers by API domain, such as `CustomerCheckoutController` or `AdminCatalogController`.
- Name controller actions after the business operation: `getProductList`, `updateProductCategory`, `placeCustomerOrder`, `markOrderAsPreparing`.
- Use `get` for reads and verbs such as `create`, `update`, `cancel`, `apply`, `place`, and `mark` for writes.
- Avoid vague names such as `save`, `process`, `handle`, or `doAction` in public controller methods. A compatibility alias may remain temporarily when an existing client depends on it; document the alias and do not add new aliases.
- Keep each class focused. Split a controller when its methods span distinct API domains.

## 5. Routes

Declare every route explicitly in `routes/api.php`. Use only `GET` and `POST`; a POST action can represent an update, status change, or deletion. Do not use `Route::resource`, `Route::apiResource`, `PUT`, `PATCH`, or `DELETE`.

```php
Route::get('/v1/products/get-product-list', [CustomerCatalogController::class, 'getProductList']);
Route::post('/v1/admin/products/update-product-category', [AdminCatalogController::class, 'updateProductCategory']);
```

Group protected customer routes under `auth:sanctum`. Protect admin routes with authentication and the narrowest applicable role middleware. Public routes must not expose customer or admin data. Keep route names and request contracts stable for the website and Flutter client; document intentional contract changes in `docs/API_STANDARDS.md` or `docs/ADMIN_API_CONTRACT.md`.

## 6. Form Requests and validation

Create a Form Request for each meaningful input contract. Form Requests own validation and request authorization. Every Form Request and each of its overridden public methods must have a short PHPDoc comment describing its purpose and important inputs.

Controllers consume `$request->validated()` (or a specific validated key). Do not call `$request->validate()`, `Validator::make()`, or read unvalidated write payloads in a controller. For GET filters, use a Form Request when filters affect data access, contain more than a trivial scalar, or need bounds/defaults.

Rules should check ownership and relationships where possible, constrain enumerated values using enums, validate paired coordinates together, and cap strings, quantities, arrays, and uploads. Client-side validation is for convenience only; server validation is authoritative. Never accept prices, totals, discounts, stock values, or permissions from a client as trusted facts.

All API Form Requests extend `ApiFormRequest` or the appropriate admin base, and return the shared validation error envelope with HTTP 422. Do not duplicate that envelope in feature requests.

## 7. Controllers

Every API controller uses `App\Traits\ApiResponseTrait` directly or through an API controller base class. Every controller method has PHPDoc describing the operation, expected caller, and relevant request data. Keep public methods short: receive validated input, call an action/service, shape output with a Resource/transformer, and return the standard response.

Do not put business rules, multi-table workflows, large queries, manual validation, file lifecycle rules, or raw table projections in controllers. Do not return full Eloquent models from a controller. For transactional writes, call an action/service that owns the transaction.

Use dependency injection for actions and services. Avoid service-locator calls such as `app(SomeController::class)` in new code. Compatibility facades may remain while routes are migrated, but should delegate to domain services/actions rather than another controller.

## 8. Actions and services

An Action represents one clear write operation, such as `PlaceCustomerOrder` or `UpdateProductCategory`. It accepts validated data or a typed DTO and the authenticated actor it needs; it enforces business invariants and returns a domain result/model for the controller to transform.

A Service contains reusable domain calculations, queries, or integrations, such as checkout pricing, catalog filtering, or payment-provider communication. Do not use a service as an unstructured collection of unrelated methods.

Actions and services must not format HTTP responses or return `JsonResponse`. Keep user-facing messages and response envelopes at the API boundary. Add PHPDoc for public classes and methods, including the operation's important arguments and result.

## 9. API response contract

Use `ApiResponseTrait` for all API controller responses.

Success:

```json
{
  "success": true,
  "message": "Products loaded successfully.",
  "data": [],
  "meta": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Product is unavailable.",
  "errors": {},
  "meta": {}
}
```

Validation error:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "quantity": ["The quantity must be at least 1."]
  },
  "meta": {}
}
```

Always include `success`, `message`, and exactly one of `data` or `errors`. Include `meta` consistently; use it for pagination, totals, applied filters, and contextual flags. Validation failures use HTTP 422. Use suitable HTTP status codes for other failures. Do not return database rows, raw Eloquent models, or framework exception pages as API payloads.

API Resources or explicit transformers must expose only client-required fields. Never expose passwords, token secrets, audit-only data, soft-delete flags, internal file paths, cost-only fields, or internal notes in customer responses.

## 10. Localization

Gujarati (`gu`) is the default customer language. Support English (`en`) and Hindi (`hi`). Clients can send `X-Locale: gu`, `X-Locale: en`, or `X-Locale: hi`; unsupported values fall back to Gujarati. Store translated customer-facing content in the established translation fields/JSON structure and select labels using the request locale. Keep locale selection centralized in middleware/services rather than repeating it in controllers.

Do not copy TeacherWay's English/Arabic language files, numeric message-code contract, or RTL assumptions. Admin forms must support editing translations for product, category, and other customer-facing content. Document locale-aware response fields in the API contract.

## 11. Enums and fixed values

Use backed enums under `app/Enums` for fixed values such as order status, payment status, fulfillment type, payment method, user/admin role, coupon type, and product availability. Do not scatter string literals or numeric magic values for these domain states.

Use enum values in validation rules, model casts when appropriate, database writes, and response mapping. A database value should remain stable if its display label is translated. Add new enum cases with the database and API compatibility impact in mind.

## 12. Traits

Traits are for behavior shared across unrelated classes, such as `ApiResponseTrait`, reusable image URL construction, or a genuinely reusable model scope. Do not put domain workflows, service dependencies, or feature-specific state changes in traits. Prefer a service/action or model method when behavior belongs to one domain.

## 13. Models and database access

Use Eloquent models and relationships for application domain records. Define explicit casts, fillable/guarded fields, relationships, and query scopes where they clarify model behavior. Keep frontend projection and business operations outside models unless the behavior is intrinsic to the model.

Avoid repeated hand-written table joins when a model relationship or focused query/service expresses ownership and loading more clearly. Eager-load relationships used in collections to avoid N+1 queries. Select only needed columns for list endpoints.

Use migrations as the source of schema changes. Preserve historical migrations after deployment; add a new migration instead of editing a migration already applied to shared environments. Do not return raw table rows from an API.

## 14. Images and uploads

`config/imagepath.php` is the sole configuration source for these groups:

- `business.logo`, `business.cover`
- `category.image`
- `product.main_image`, `product.gallery`, `product.custom_uploads`
- `banner.image`, `review.image`, `payment.proof`, `user.avatar`

Do not hard-code image directories or make the frontend assemble storage paths. Models with images expose safe URL attributes such as `image_url`, `logo_url`, `gallery_urls`, or `avatar_url`; Resources return those URLs and omit raw filenames/paths unless an admin operation needs them.

Validate file type, size, and required dimensions when relevant. Store files on the configured disk. When replacing/removing an image, update the database and clean the old file safely; for multi-row operations, keep database changes transactional and handle storage cleanup so failed writes do not leave inconsistent references.

## 15. Transactions, errors, and side effects

Use `DB::transaction()` for multi-table operations and state changes that must succeed together: checkout, order placement, payment capture/refund, coupon redemption, inventory changes, and order transitions with history. Keep the transaction inside the Action/Service that owns the complete operation.

Use specific exceptions or validation errors for expected domain failures. Do not swallow exceptions. Log unexpected failures with useful context while keeping secrets and personal data out of logs. Send notifications, broadcasts, and other external side effects after a successful commit or through queued events; a failed notification must not undo a valid order.

## 16. Authentication and authorization

Use Sanctum for customer/admin API tokens. Customer tokens receive `customer-access`; admin tokens receive `admin-access`, `admin:read`, and `admin:write`. Apply `auth:sanctum` and the matching `ability` middleware to protected route groups. Admin middleware also checks the read/write ability that matches the HTTP method and applies role-specific restrictions. Customer middleware requires an active customer account. Broadcast channel auth accepts only the matching customer/admin abilities and then checks customer ownership or admin role. Check ownership on every customer-owned address, cart, upload, order, favorite, and support record. Never rely on a hidden UI control as authorization.

Issue tokens only after successful authentication. Limit token abilities and lifetime according to the client use case. Rate-limit OTP and login endpoints; do not log OTP values, passwords, bearer tokens, or full payment credentials.

## 17. PHP style

- Follow PSR-12 and the repository's Laravel Pint configuration.
- Use strict, descriptive names and explicit imports.
- Prefer early returns for guard clauses and avoid deeply nested conditionals.
- Add scalar/return types to new code where compatible with Laravel conventions.
- Keep methods small and avoid unexplained magic values.
- Add PHPDoc to API controller methods, Form Requests, Actions, Services, and non-obvious model behavior.
- Do not add comments that merely repeat the method name; document caller, inputs, invariants, or side effects.

## 18. Documentation and delivery

After a meaningful backend change:

- Update `docs/DEVELOPMENT_TRACKER.md` with scope, files/modules, outcome, checks performed, and remaining work.
- Update `docs/API_STANDARDS.md` or `docs/ADMIN_API_CONTRACT.md` when routes, parameters, authorization, or response fields change.
- Update `docs/DATABASE_DESIGN.md` when persisted schema changes.
- Update `docs/PROJECT_REBUILD_PLAN.md` when scope, priorities, or milestones change.

Do not mark a phase complete until its required backend or frontend checks and important flow verification have been run and recorded. Do not claim checks that were not performed.

## 19. New endpoint checklist

- [ ] Add an explicit GET or POST route in the correct public/customer/admin middleware group.
- [ ] Add a Form Request for inputs and ownership-sensitive authorization.
- [ ] Add or reuse an Action/Service for business logic.
- [ ] Use enums for fixed domain values and a transaction for coupled writes.
- [ ] Return an API Resource/transformer through `ApiResponseTrait`.
- [ ] Add PHPDoc to the controller method and Form Request methods.
- [ ] Return only fields needed by the consuming screen and locale-aware labels where applicable.
- [ ] Update the API contract and development tracker.
- [ ] Run the relevant checks and record their actual results.

## 20. New image field checklist

- [ ] Add the directory to the correct `config/imagepath.php` group.
- [ ] Validate upload type and size in a Form Request.
- [ ] Store through the configured disk and handle replacement/removal.
- [ ] Add a frontend-safe model URL accessor.
- [ ] Expose the URL through a Resource; keep the raw path private.
- [ ] Update database/API docs and the development tracker.
