<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Persist configurable product message, photo, flavour, egg and scheduling controls. */
class ProductCustomizationOption extends Model
{
    protected $table = 'product_customization_options';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'message_label_translations' => 'array',
            'photo_upload_label_translations' => 'array',
            'allow_message' => 'boolean',
            'allow_photo_upload' => 'boolean',
            'allow_flavour_selection' => 'boolean',
            'allow_egg_selection' => 'boolean',
            'allow_delivery_date_selection' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
