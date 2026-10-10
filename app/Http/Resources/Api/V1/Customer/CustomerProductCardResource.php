<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Services\CustomerCatalogService;
use App\Support\ImagePath;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose the minimal product fields used in customer product cards. */
class CustomerProductCardResource extends JsonResource
{
    /** Build the existing product-card response without leaking catalog columns. */
    public function toArray($request): array
    {
        $catalog = app(CustomerCatalogService::class);

        return [
            'id' => $this->id,
            'name' => $catalog->translated($this->name_translations),
            'slug' => $this->slug,
            'price_text' => 'From Rs. '.number_format((float) $this->base_price, 0),
            'rating' => null,
            'meta' => $catalog->translated($this->short_description_translations) ?: $this->min_preparation_minutes.' min',
            'image_url' => ImagePath::url($this->main_image_path),
            'is_available' => (bool) $this->is_available,
        ];
    }
}
