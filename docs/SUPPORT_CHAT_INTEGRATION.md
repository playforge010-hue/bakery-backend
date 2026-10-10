# Support live chat integration — 2026-10-07

Owned implementation is in the dedicated Support controllers, FormRequests, services,
models, SupportChatChanged event, migration, frontend components/support and both
support pages. Shared files have not been edited by this slice. Existing order
broadcasts and realtime.ts are reused unchanged. The parent owns migration execution
and the combined runtime smoke check; this slice does not run migrate or tests.

## routes/api.php

Add imports:

```php
use App\Http\Controllers\Api\V1\Customer\CustomerSupportController;
use App\Http\Controllers\Api\V1\Admin\AdminSupportController;
```

Inside the existing customer group (`auth:sanctum`, `ability:customer-access`,
`customer.role`), replace the existing `/v1/support/create-ticket` route targeting
CustomerEngagementController and add:

```php
Route::get('/v1/support/get-ticket-list', [CustomerSupportController::class, 'getTicketList']);
Route::get('/v1/support/get-ticket-detail', [CustomerSupportController::class, 'getTicketDetail']);
Route::post('/v1/support/create-ticket', [CustomerSupportController::class, 'createSupportTicket'])->middleware('throttle:20,1');
Route::post('/v1/support/reply-ticket', [CustomerSupportController::class, 'replyTicket'])->middleware('throttle:60,1');
Route::post('/v1/support/mark-messages-read', [CustomerSupportController::class, 'markMessagesRead'])->middleware('throttle:120,1');
Route::post('/v1/support/send-typing-indicator', [CustomerSupportController::class, 'sendTypingIndicator'])->middleware('throttle:120,1');
```

Remove the four existing support routes targeting AdminOperationsController from
the `owner,admin` group. Add the following to the existing
`auth:sanctum`, `ability:admin-access`, `admin.role:owner,admin,staff` group with
prefix `v1/admin`. Keep the original paths so the legacy endpoint constants work:

```php
Route::get('/support/get-ticket-list', [AdminSupportController::class, 'getTicketList']);
Route::get('/support/get-ticket-detail', [AdminSupportController::class, 'getTicketDetail']);
Route::post('/support/reply-ticket', [AdminSupportController::class, 'replyTicket'])->middleware('throttle:60,1');
Route::post('/support/update-ticket-status', [AdminSupportController::class, 'updateTicketStatus'])->middleware('throttle:60,1');
Route::post('/support/mark-messages-read', [AdminSupportController::class, 'markMessagesRead'])->middleware('throttle:120,1');
Route::post('/support/send-typing-indicator', [AdminSupportController::class, 'sendTypingIndicator'])->middleware('throttle:120,1');
```

Add a shared authenticated image GET endpoint OUTSIDE role-specific groups. It
accepts either audience, and the FormRequest/service checks active role, token
abilities and ticket ownership. This is a binary image response on success; errors
retain the normal JSON API envelope.

```php
Route::get('/v1/support/get-attachment', [CustomerSupportController::class, 'getAttachment'])
    ->middleware(['auth:sanctum', 'abilities:customer-access,admin-access', 'throttle:240,1']);
```

`abilities` is the existing CheckForAnyAbility alias; do not use CheckAbilities to
require a token to hold both customer-access and admin-access. The existing
broadcasting auth endpoint also needs either audience, not both.

## routes/channels.php

Add imports and callbacks; do not change the existing order channels:

```php
use App\Services\Support\SupportAccess;

Broadcast::channel('admin.support', function (User $user): bool {
    return SupportAccess::isAdmin($user);
});

Broadcast::channel('support.{ticketNumber}', function (User $user, string $ticketNumber): bool {
    return SupportAccess::canViewTicket($user, $ticketNumber);
});
```

Admin access: active owner/admin/staff, `admin-access` AND `admin:read`. Customer
access: active customer, `customer-access` AND ownership of that ticket. Admin
HTTP mutations additionally require `admin:write`; reads require `admin:read`.
The support service checks these abilities independently of middleware.

Support broadcasts use `.support.changed` and the `change_type` values `created`,
`message`, `read`, `status`, `typing`. Durable events go to both private channels;
typing goes only to the ticket channel, with the sender type determined on the
server. Typing signals expire after five seconds in the UI.

## app/Http/Middleware/EnsureAdminRole.php

Parent integration: add `support/mark-messages-read` and
`support/send-typing-indicator` to the staff POST allowlist. Existing list/detail,
reply/status paths are already allowed. No staff attachment whitelist is needed:
the shared image GET endpoint uses SupportAccess instead of admin.role middleware.

## config/imagepath.php

Add this top-level group (keep existing disk/directories unchanged):

```php
'support' => [
    'disk' => 'local',
    'directory' => 'support/messages',
],
```

Use a private disk. New attachment model accessors produce the authenticated
`/api/v1/support/get-attachment?attachment_id=...` URL and never expose raw paths.
The UI fetches these lazily with its session bearer token and uses revocable blob
URLs. Existing single attachment_path values keep their imagepath.disk URL
accessor for compatibility. The service has the above defaults until merged.

## lib/api.ts and lib/admin-api.ts

No transport changes are required. The owned typed adapter
`components/support/support-api.ts` reuses apiGet/apiPost/apiPostForm/adminRequest.
If parent wants all endpoint constants listed in the existing shared clients,
add these aliases (the owned adapter already works without them):

```ts
// endpoints in lib/api.ts; existing support key remains create-ticket
supportTickets: "/v1/support/get-ticket-list",
supportTicketDetail: "/v1/support/get-ticket-detail",
supportReply: "/v1/support/reply-ticket",
supportRead: "/v1/support/mark-messages-read",
supportTyping: "/v1/support/send-typing-indicator",

// adminEndpoints in lib/admin-api.ts; existing four support keys remain valid
readTicket: "/v1/admin/support/mark-messages-read",
ticketTyping: "/v1/admin/support/send-typing-indicator",
```

## Migration and runtime contract

Parent runs migration `2026_10_07_000010_add_support_live_chat.php` after merging
routes/config/auth. It adds category and nullable client_message_id to tickets,
client_message_id/read_at to messages, indexes and the private attachments table.
Legacy data remains intact. Existing non-atomic support controller methods are
left untouched outside this slice; shared routes must point to the new controllers.

Message/create requests require `client_message_id` (UUID). Reuse it with the SAME
text/images on every retry. Creation also requires subject/category; optional
order_number is checked against the authenticated customer. Text is optional when
images are supplied. Upload multipart `images[]`: one to six JPG/PNG/WebP files,
each <=5 MiB, combined <=20 MiB, dimensions <=6000x6000. SVG is rejected. Ensure
PHP upload_max_filesize >=5M and post_max_size >=24M, and matching proxy body limits.

List supports page/per_page <=50, search <=190, status. Detail supports
ticket_number, limit <=100 and either before_id or after_id. It returns
`data: {ticket, messages}`, with `meta: {has_more, oldest_id, latest_id, direction}`.
Send/create returns `data: {ticket, message, created}`; first send is 201 and
duplicate retry is 200. Read uses ticket_number/up_to_message_id and only changes
opposite-side messages at or below a cursor belonging to that ticket. Admin read
receipts mean the bakery team has read the message. Resolved requests reopen on a
new message; closed requests reject new sends, while duplicate retries still
return their original message. Admin can reopen a closed request via status.

Realtime delivery errors are logged after commit and do not turn persisted sends
into HTTP failures. UI polls visible conversations every 30s when live, every 5s
when disconnected, reconciles on reconnect/focus, and merges by server message id
and client_message_id. Inbox events are debounced and use one shared Echo socket
with the active chat. Gujarati is the customer default; English/Hindi are available.

## Parent runtime checks after merging

No automated tests are added or run. Check authenticated customer/admin/staff
create/list/detail/send/read/status/typing and private-channel auth. Send text plus
two images; retry the same UUID and confirm one row/message and no duplicate files.
Check image-only messages, unauthorized ticket/image requests, invalid MIME/SVG,
dimensions/count/size limits, history pagination and closed-ticket behavior. Verify
both live UIs, typing expiry, read state, failed-send retry and Reverb reconnect.

Verification results from this slice will be appended below.
