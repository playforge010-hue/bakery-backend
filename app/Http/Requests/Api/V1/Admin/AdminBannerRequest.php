<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Validation\Rule;

/** Validate homepage banner copy, destination rules and configured image uploads. */
class AdminBannerRequest extends AdminFormRequest
{
    use \App\Traits\NormalizesCatalogPayload;
    /** Route role middleware authorizes admin access before banner validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $isUpdate = basename($this->path()) === 'update-banner';

        return [
            'id' => [$isUpdate ? 'required' : 'sometimes', 'integer', Rule::exists('banners', 'id')],
            'title_translations' => ['required', 'array'],
            'subtitle_translations' => ['nullable', 'array'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'link_type' => ['required', Rule::in(['none', 'url', 'category', 'product'])],
            'link_value' => [
                'required_unless:link_type,none', 'nullable', 'string', 'max:500',
                ...match ($this->input('link_type')) {
                    'url' => ['url:http,https'],
                    'category' => [Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
                    'product' => [Rule::exists('products', 'id')->whereNull('deleted_at')],
                    default => [],
                },
            ],
            'remove_image' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            ...$this->translationRules('title_translations', true),
            ...$this->translationRules('subtitle_translations', false, 10000),
        ];
    }
}
