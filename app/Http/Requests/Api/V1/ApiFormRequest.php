<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Preserve the project's standard API envelope for automatic Form Request failures. */
abstract class ApiFormRequest extends FormRequest
{
    /** Return validation errors in the consistent API error shape. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
            'meta' => (object) [],
        ], 422));
    }
}
