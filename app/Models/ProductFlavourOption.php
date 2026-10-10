<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFlavourOption extends Model
{
    protected $table = 'product_flavour_options';

    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function flavour()
    {
        return $this->belongsTo(ProductFlavour::class, 'product_flavour_id');
    }
}
