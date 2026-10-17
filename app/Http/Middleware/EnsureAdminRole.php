<?php

namespace App\Http\Middleware;

use App\Enums\AdminRole;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Enforce active back-office roles and token scopes for admin API actions. */
class EnsureAdminRole
{
    use ApiResponseTrait;

    /** Ensure the authenticated account is active and has one of the allowed back-office roles. */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active || ! in_array($user->role, $roles ?: AdminRole::managementRoles(), true)) {
            return $this->errorResponse('You are not authorized to use the administration API.', [], [], 403);
        }
        $requiredAbility = $request->isMethod('GET') ? 'admin:read' : 'admin:write';
        if (! $user->tokenCan($requiredAbility)) {
            return $this->errorResponse('This access token cannot perform that administration action.', [], [], 403);
        }
        if ($user->role === AdminRole::Staff->value && ! $this->staffMayUse($request)) {
            return $this->errorResponse('This staff role cannot perform that administration action.', [], [], 403);
        }

        return $next($request);
    }

    /** Keep general staff limited to order and customer-support operations. */
    private function staffMayUse(Request $request): bool
    {
        $path = Str::after($request->path(), 'v1/admin/');
        if ($request->isMethod('GET')) {
            return in_array($path, [
                'dashboard/get-dashboard-summary',
                'orders/get-order-list',
                'orders/get-dispatcher-orders',
                'orders/get-order-changes',
                'orders/get-order-detail',
                'orders/export-order-list',
                'categories/get-category-list',
                'products/get-product-list',
                'support/get-ticket-list',
                'support/get-ticket-detail',
                'support/get-attachment',
            ], true);
        }
        if (! $request->isMethod('POST')) {
            return false;
        }

        return in_array($path, [
            'auth/logout-admin', 'orders/update-order-status', 'orders/accept-order', 'orders/mark-order-as-preparing',
            'orders/mark-order-as-ready', 'orders/mark-order-as-out-for-delivery', 'orders/mark-order-as-delivered',
            'orders/mark-order-as-picked-up', 'orders/cancel-order', 'orders/update-order-internal-note',
            'support/reply-ticket', 'support/update-ticket-status',
            'support/mark-messages-read', 'support/send-typing-indicator',
        ], true);
    }
}
