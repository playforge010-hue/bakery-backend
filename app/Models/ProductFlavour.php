<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFlavour extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['name_translations' => 'array', 'description_translations' => 'array', 'extra_price' => 'decimal:2', 'is_active' => 'boolean'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_flavour_options')->withPivot('is_default');
    }
}
