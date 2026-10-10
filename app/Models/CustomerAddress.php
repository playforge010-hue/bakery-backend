<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use SoftDeletes;

    protected $table = 'customer_addresses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_default' => 'boolean'];
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deliveryZone()
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    /** Limit address queries to records that have not been soft deleted. */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
}
