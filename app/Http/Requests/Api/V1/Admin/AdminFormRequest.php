<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Traits\ApiResponseTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Base admin form request that preserves the API's standard validation error envelope. */
abstract class AdminFormRequest extends FormRequest
{
    use ApiResponseTrait;

    /** Render validation failures using the standard success/message/errors/meta response shape. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException($this->errorResponse('Validation failed.', $validator->errors()->toArray(), [], 422));
    }
}
