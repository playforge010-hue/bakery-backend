<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductInventory extends Model
{
    protected $table = 'product_inventory';

    protected $guarded = ['id'];

    protected $casts = ['track_stock' => 'boolean', 'is_sold_out' => 'boolean', 'sold_out_until' => 'datetime'];

    public function getIsAvailableForOrderAttribute(): bool
    {
        return ! $this->is_sold_out
            && (! $this->sold_out_until || ! $this->sold_out_until->isFuture())
            && (! $this->track_stock || ($this->stock_quantity !== null && (int) $this->stock_quantity > 0));
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
