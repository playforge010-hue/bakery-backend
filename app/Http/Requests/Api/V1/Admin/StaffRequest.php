<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Validation\Rule;

/** Validate staff provisioning and role changes against supported least-privilege roles. */
class StaffRequest extends AdminFormRequest
{
    /** Route role middleware authorizes admin access before staff validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Owner/admin staff administration payload. */
    public function rules(): array
    {
        return ['id' => [
            'sometimes',
            'integer',
            'exists:users,id',
        ],
            'name' => [
                'required',
                'string',
                'max:190',
            ],
            'mobile_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('users',
                    'mobile_number')->ignore($this->input('id')),
            ],
            'email' => [
                'nullable',
                'email',
                'max:190',
                Rule::unique('users',
                    'email')->ignore($this->input('id')),
            ],
            'password' => [
                $this->input('id') ? 'nullable' : 'required',
                'string',
                'min:12',
                'max:255',
            ],
            'role' => ['required', Rule::in([
                'admin',
                'staff',
                'delivery',
            ])],
            'is_active' => [
                'sometimes',
                'boolean',
            ]];
    }
}
