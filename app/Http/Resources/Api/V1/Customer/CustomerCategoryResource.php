<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Services\CustomerCatalogService;
use App\Support\ImagePath;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose only category fields consumed by customer browsing screens. */
class CustomerCategoryResource extends JsonResource
{
    /** Build the stable customer category card payload. */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => app(CustomerCatalogService::class)->translated($this->name_translations),
            'slug' => $this->slug,
            'image_url' => ImagePath::url($this->image_path),
        ];
    }
}
