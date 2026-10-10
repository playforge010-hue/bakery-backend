<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Services\CustomerCatalogService;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose scheduled homepage banner content and its resolved public image URL. */
class CustomerBannerResource extends JsonResource
{
    /** Build the homepage banner payload used by the storefront. */
    public function toArray($request): array
    {
        $catalog = app(CustomerCatalogService::class);

        return [
            'id' => $this->id,
            'title' => $catalog->translated($this->title_translations),
            'subtitle' => $catalog->translated($this->subtitle_translations),
            'image_url' => $this->image_url,
            'link_type' => $this->link_type,
            'link_value' => $this->link_value,
        ];
    }
}
