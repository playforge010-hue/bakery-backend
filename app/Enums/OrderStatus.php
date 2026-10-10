<?php

namespace App\Enums;

/** Persisted customer order lifecycle states. */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    /** Return states staff may request as a transition target. */
    public static function staffTargets(): array
    {
        return [
            self::Accepted->value,
            self::Preparing->value,
            self::Ready->value,
            self::OutForDelivery->value,
            self::Delivered->value,
            self::PickedUp->value,
            self::Cancelled->value,
        ];
    }
}
