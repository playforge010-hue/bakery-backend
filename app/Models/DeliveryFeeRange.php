<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryFeeRange extends Model
{
    protected $fillable = [
        'minimum_distance_km',
        'maximum_distance_km',
        'delivery_fee',
        'estimated_delivery_minutes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'minimum_distance_km' => 'decimal:2',
            'maximum_distance_km' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'estimated_delivery_minutes' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
