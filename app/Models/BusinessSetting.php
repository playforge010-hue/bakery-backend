<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\ImagePath;

class BusinessSetting extends Model
{
    protected $fillable = [
        'bakery_name',
        'logo_path',
        'cover_image_path',
        'address_line_1',
        'address_line_2',
        'area',
        'city',
        'state',
        'pincode',
        'latitude',
        'longitude',
        'phone_number',
        'whatsapp_number',
        'email',
        'gst_number',
        'fssai_number',
        'minimum_order_amount',
        'max_delivery_distance_km',
        'default_preparation_minutes',
        'delivery_available',
        'takeaway_available',
        'online_payment_available',
        'cod_available',
        'upi_available',
        'maintenance_mode',
        'maintenance_message',
        'social_links_json',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'minimum_order_amount' => 'decimal:2',
            'max_delivery_distance_km' => 'decimal:2',
            'social_links_json' => 'array',
            'delivery_available' => 'boolean',
            'takeaway_available' => 'boolean',
            'online_payment_available' => 'boolean',
            'cod_available' => 'boolean',
            'upi_available' => 'boolean',
            'maintenance_mode' => 'boolean',
        ];
    }

    protected $appends = ['logo_url', 'cover_image_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->imageUrl($this->logo_path, 'business.logo');
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->imageUrl($this->cover_image_path, 'business.cover');
    }

    private function imageUrl(?string $path, string $key): ?string
    {
        return ImagePath::url($path) ?? config('imagepath.defaults.'.$key);
    }
}
