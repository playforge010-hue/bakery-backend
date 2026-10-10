<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['customization_summary_json' => 'array'];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function flavour()
    {
        return $this->belongsTo(ProductFlavour::class, 'selected_flavour_id');
    }

    public function addons()
    {
        return $this->hasMany(CartItemAddon::class);
    }

    public function uploads()
    {
        return $this->hasMany(CartItemUpload::class);
    }
}
