<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['customization_summary_json' => 'array', 'unit_price' => 'decimal:2', 'addon_total' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function addons()
    {
        return $this->hasMany(OrderItemAddon::class);
    }

    public function uploads()
    {
        return $this->hasMany(OrderItemUpload::class);
    }
}
