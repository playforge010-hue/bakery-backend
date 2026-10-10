<?php

use App\Models\Order;
use App\Models\User;
use App\Enums\AdminRole;
use App\Services\Support\SupportAccess;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin.orders', function (User $user): bool {
    return in_array($user->role, AdminRole::managementRoles(), true)
        && (bool) $user->is_active
        && $user->tokenCan('admin-access')
        && $user->tokenCan('admin:read');
});

Broadcast::channel('orders.{orderNumber}', function (User $user, string $orderNumber): bool {
    if ($user->role !== 'customer' || ! $user->is_active || ! $user->tokenCan('customer-access')) {
        return false;
    }

    return Order::query()
        ->where('order_number', $orderNumber)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('admin.support', function (User $user): bool {
    return SupportAccess::isAdmin($user);
});

Broadcast::channel('support.{ticketNumber}', function (User $user, string $ticketNumber): bool {
    return SupportAccess::canViewTicket($user, $ticketNumber);
});
