<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = [
        'name',
        'area_names_json',
        'pincodes_json',
        'center_latitude',
        'center_longitude',
        'radius_km',
        'minimum_order_amount',
        'delivery_fee',
        'free_delivery_above',
        'estimated_delivery_minutes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'area_names_json' => 'array',
            'pincodes_json' => 'array',
            'is_active' => 'boolean',
            'center_latitude' => 'decimal:7',
            'center_longitude' => 'decimal:7',
            'radius_km' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'free_delivery_above' => 'decimal:2',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }
}
