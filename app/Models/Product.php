<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\ImagePath;

class Product extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'name_translations' => 'array',
        'short_description_translations' => 'array',
        'description_translations' => 'array',
        'base_price' => 'decimal:2',
        'price_starts_from' => 'boolean',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'is_bestseller' => 'boolean',
        'is_new' => 'boolean',
        'is_customizable' => 'boolean',
        'allow_message' => 'boolean',
        'allow_photo_upload' => 'boolean',
        'is_eggless_available' => 'boolean',
        'is_egg_available' => 'boolean',
        'sold_out_until' => 'datetime',
    ];

    protected $appends = ['image_url', 'main_image_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function flavourOptions(): HasMany
    {
        return $this->hasMany(ProductFlavourOption::class);
    }

    public function addonOptions(): HasMany
    {
        return $this->hasMany(ProductAddonOption::class);
    }

    public function flavours(): BelongsToMany
    {
        return $this->belongsToMany(ProductFlavour::class, 'product_flavour_options')->withPivot('is_default');
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(ProductAddon::class, 'product_addon_options')->withPivot('is_required', 'max_quantity');
    }

    public function customization(): HasOne
    {
        return $this->hasOne(ProductCustomizationOption::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(ProductInventory::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(ProductInventory::class)->whereNull('product_variant_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->where(fn ($q) => $q->whereNull('sold_out_until')->orWhere('sold_out_until', '<=', now()));
    }

    public function getMainImageUrlAttribute(): ?string
    {
        return $this->getImageUrlAttribute();
    }

    public function getImageUrlAttribute(): ?string
    {
        return ImagePath::url($this->main_image_path);
    }
}
