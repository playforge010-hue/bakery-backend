<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Services\Admin\AdminDispatcherService;
use Illuminate\Validation\Rule;

/** Validate the dispatcher's queue and pagination independently of report date filters. */
class DispatcherOrdersRequest extends AdminFormRequest
{
    /** Access is restricted to active bakery staff by the route middleware. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Accept only known operational queues and bounded page sizes. */
    public function rules(): array
    {
        return [
            'queue' => ['sometimes', Rule::in(['all', ...array_keys(AdminDispatcherService::QUEUES)])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
