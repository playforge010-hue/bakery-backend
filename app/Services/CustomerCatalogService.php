<?php

namespace App\Services;

use App\Http\Resources\Api\V1\Customer\CustomerCategoryResource;
use App\Http\Resources\Api\V1\Customer\CustomerProductCardResource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read and shape the customer-facing catalog without exposing database records. */
class CustomerCatalogService
{
    /** Return active category cards in their configured display order. */
    public function categoryCards(): array
    {
        return DB::table('product_categories')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($category) => CustomerCategoryResource::make($category)->resolve())->values()->all();
    }

    /** Return available product cards filtered by the existing search/category query contract. */
    public function productCards(array $filters = []): array
    {
        return $this->productQuery($filters)
            ->get()
            ->map(fn ($product) => $this->productCard($product))
            ->values()
            ->all();
    }

    /** Return one filtered product page and its navigation metadata. */
    public function paginatedProductCards(array $filters, int $perPage): array
    {
        $products = $this->productQuery($filters)
            ->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));

        return [
            'items' => $products->getCollection()
                ->map(fn ($product) => $this->productCard($product))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ];
    }

    /** Build the filtered query shared by regular and paginated catalog reads. */
    private function productQuery(array $filters): Builder
    {
        $query = DB::table('products')
            ->where('is_available', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order');

        if (! empty($filters['search'])) {
            $query->where('name_translations', 'like', '%'.strtolower((string) $filters['search']).'%');
        }

        if (! empty($filters['category'])) {
            $query->whereIn('product_category_id', DB::table('product_categories')
                ->where('slug', (string) $filters['category'])->where('is_active', true)->select('id'));
        }

        if (! empty($filters['featured'])) {
            $query->where('is_featured', true);
        }

        return $query;
    }

    /** Project one catalog record into the frontend's established product-card shape. */
    public function productCard(object $product): array
    {
        return CustomerProductCardResource::make($product)->resolve();
    }

    /** Resolve translated JSON using the same locale fallback order as the prior API. */
    public function translated(array|string|null $json, ?string $locale = null): string
    {
        if (! $json) {
            return '';
        }
        $values = is_array($json) ? $json : json_decode($json, true);
        if (! is_array($values)) {
            return $json;
        }

        $locale ??= app()->getLocale();

        return $values[$locale] ?? $values['gu'] ?? $values['en'] ?? $values['hi'] ?? collect($values)->first() ?? '';
    }
}
