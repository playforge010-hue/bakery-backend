<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAddonOption extends Model
{
    protected $table = 'product_addon_options';

    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function addon()
    {
        return $this->belongsTo(ProductAddon::class, 'product_addon_id');
    }
}
