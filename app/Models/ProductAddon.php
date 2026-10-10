<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\ImagePath;

class ProductAddon extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['name_translations' => 'array', 'description_translations' => 'array', 'price' => 'decimal:2', 'is_active' => 'boolean'];

    protected $appends = ['image_url'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_addon_options')->withPivot('is_required', 'max_quantity');
    }

    public function getImageUrlAttribute(): ?string
    {
        return ImagePath::url($this->image_path);
    }
}
