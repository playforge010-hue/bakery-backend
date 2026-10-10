<?php

namespace App\Enums;

/** Supported coupon discount calculation types. */
enum CouponType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
