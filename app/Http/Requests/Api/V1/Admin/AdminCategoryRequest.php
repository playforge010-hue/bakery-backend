<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Validation\Rule;

/** Validate localized category create/update forms and safe uploaded artwork. */
class AdminCategoryRequest extends AdminFormRequest
{
    use \App\Traits\NormalizesCatalogPayload;
    /** Route role middleware authorizes admin access before category validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Category fields accepted by the admin catalog forms. */
    public function rules(): array
    {
        $update = basename($this->path()) === 'update-category';

        return ['id' => [
            $update ? 'required' : 'sometimes',
            'integer',
            'exists:product_categories,id',
        ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:product_categories,id',
            ],
            'name_translations' => [
                'required',
                'array',
            ],
            'name_translations.gu' => [
                'sometimes',
                'string',
                'max:190',
            ],
            'name_translations.en' => [
                'sometimes',
                'string',
                'max:190',
            ],
            'name_translations.hi' => [
                'sometimes',
                'string',
                'max:190',
            ],
            'description_translations' => [
                'nullable',
                'array',
            ],
            'slug' => [
                'sometimes',
                'string',
                'max:190',
                Rule::unique('product_categories',
                    'slug')->ignore($this->input('id')),
            ],
            'is_featured' => [
                'sometimes',
                'boolean',
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
            'image' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'remove_image' => ['sometimes', 'boolean'],
            'remove_banner_image' => ['sometimes', 'boolean'],
            'banner_image' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
            ],
            ...$this->translationRules('name_translations', true),
            ...$this->translationRules('description_translations', false, 10000),
        ];
    }
}
