<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\ImagePath;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'image_path', 'alt_text_translations', 'is_primary', 'sort_order'];

    protected function casts(): array
    {
        return ['alt_text_translations' => 'array', 'is_primary' => 'boolean'];
    }

    protected $appends = ['image_url'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return ImagePath::url($this->image_path);
    }
}
