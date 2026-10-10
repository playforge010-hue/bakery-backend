<?php

namespace App\Enums;

/** Roles assigned to the bakery owner and back-office operators. */
enum AdminRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';
    case Delivery = 'delivery';

    /** Return roles that may sign in to the admin portal. */
    public static function managementRoles(): array
    {
        return [self::Owner->value, self::Admin->value, self::Staff->value];
    }
}
