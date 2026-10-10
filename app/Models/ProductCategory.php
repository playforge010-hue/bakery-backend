<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\ImagePath;

class ProductCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name_translations',
        'slug',
        'description_translations',
        'image_path',
        'banner_image_path',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['name_translations' => 'array', 'description_translations' => 'array', 'is_featured' => 'boolean', 'is_active' => 'boolean'];
    }

    protected $appends = ['image_url', 'banner_image_url'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->pathUrl($this->image_path, 'category.image');
    }

    public function getBannerImageUrlAttribute(): ?string
    {
        return $this->pathUrl($this->banner_image_path, 'category.image');
    }

    private function pathUrl(?string $path, string $key): ?string
    {
        return ImagePath::url($path) ?? config('imagepath.defaults.'.$key);
    }
}
