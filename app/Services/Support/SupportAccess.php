<?php

namespace App\Services\Support;

use App\Enums\AdminRole;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** Apply identical account and token rules to HTTP operations and private channels. */
class SupportAccess
{
    public static function isAdmin(User $user, bool $write = false): bool
    {
        return $user->is_active
            && in_array($user->role, AdminRole::managementRoles(), true)
            && $user->tokenCan('admin-access')
            && $user->tokenCan($write ? 'admin:write' : 'admin:read');
    }

    public static function isCustomer(User $user): bool
    {
        $hasCustomerAccess = $user->tokenCan('customer-access')
            || ($user->tokenCan('mobile-customer-access') && $user->tokenCan('mobile:read'));

        return $user->is_active && $user->role === 'customer' && $hasCustomerAccess;
    }

    public static function canViewTicket(User $user, string $ticketNumber): bool
    {
        if (self::isAdmin($user)) {
            return SupportTicket::query()->where('ticket_number', $ticketNumber)->exists();
        }

        return self::isCustomer($user) && SupportTicket::query()
            ->where('ticket_number', $ticketNumber)->where('user_id', $user->id)->exists();
    }

    public static function senderType(User $user, bool $write = false): string
    {
        if (self::isAdmin($user, $write)) {
            return SupportMessage::ADMIN;
        }
        if (self::isCustomer($user)) {
            return SupportMessage::CUSTOMER;
        }

        throw new AuthorizationException('This account cannot access support chat.');
    }
}
