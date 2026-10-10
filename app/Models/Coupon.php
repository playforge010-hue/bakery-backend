<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'title_translations',
        'description_translations',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'minimum_order_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_limit_per_customer',
        'applies_to_fulfillment_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'title_translations' => 'array',
            'description_translations' => 'array',
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
