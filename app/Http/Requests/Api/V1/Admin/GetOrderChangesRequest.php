<?php

namespace App\Http\Requests\Api\V1\Admin;

/** Validate the monotonic order-history cursor used by browser alert polling. */
class GetOrderChangesRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'after_id' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
