<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Return editable business settings with safe image URLs and no storage paths. */
class BusinessSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bakery_name' => $this->bakery_name,
            'logo_url' => $this->logo_url,
            'cover_image_url' => $this->cover_image_url,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'area' => $this->area,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'phone_number' => $this->phone_number,
            'whatsapp_number' => $this->whatsapp_number,
            'email' => $this->email,
            'gst_number' => $this->gst_number,
            'fssai_number' => $this->fssai_number,
            'minimum_order_amount' => (float) $this->minimum_order_amount,
            'max_delivery_distance_km' => (float) $this->max_delivery_distance_km,
            'default_preparation_minutes' => (int) $this->default_preparation_minutes,
            'delivery_available' => (bool) $this->delivery_available,
            'takeaway_available' => (bool) $this->takeaway_available,
            'online_payment_available' => (bool) $this->online_payment_available,
            'cod_available' => (bool) $this->cod_available,
            'upi_available' => (bool) $this->upi_available,
            'maintenance_mode' => (bool) $this->maintenance_mode,
            'maintenance_message' => $this->maintenance_message,
            'social_links_json' => $this->social_links_json,
        ];
    }
}
