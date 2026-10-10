<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Validation\Rule;

/** Validate reusable flavour and add-on definitions managed independently of products. */
class AdminProductOptionRequest extends AdminFormRequest
{
    use \App\Traits\NormalizesCatalogPayload;
    /** Route role middleware authorizes admin access before option validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Localized option details and optional configured image upload. */
    public function rules(): array
    {
        $isAddon = str_contains($this->path(), 'addon');
        $table = $isAddon ? 'product_addons' : 'product_flavours';
        $hasImage = $isAddon;

        return ['id' => [
            str_starts_with(basename($this->path()),
                'update-') ? 'required' : 'sometimes',
            'integer',
            Rule::exists($table,
                'id'),
        ],
            'name_translations' => [
                'required',
                'array',
            ],
            'description_translations' => [
                'nullable',
                'array',
            ],
            'extra_price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'price' => [
                $isAddon ? 'required' : 'prohibited',
                'numeric',
                'min:0',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
            'remove_image' => [$isAddon ? 'sometimes' : 'prohibited', 'boolean'],
            'image' => [
                $hasImage ? 'sometimes' : 'prohibited',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            ...$this->translationRules('name_translations', true),
            ...$this->translationRules('description_translations', false, 10000),
        ];
    }
}
