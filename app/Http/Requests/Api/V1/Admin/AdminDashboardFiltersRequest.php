<?php

namespace App\Http\Requests\Api\V1\Admin;

/** Validate a dashboard reporting window before calculating business totals. */
class AdminDashboardFiltersRequest extends AdminFormRequest
{
    /** Route role middleware authorizes admin access before dashboard filtering. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'range' => [
                'sometimes',
                'in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,all,custom',
            ],
            'from_date' => [
                'required_if:range,custom',
                'nullable',
                'date_format:Y-m-d',
            ],
            'to_date' => [
                'required_if:range,custom',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:from_date',
            ],
        ];
    }
}
