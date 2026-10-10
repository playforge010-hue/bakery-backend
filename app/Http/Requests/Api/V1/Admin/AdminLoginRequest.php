<?php

namespace App\Http\Requests\Api\V1\Admin;

/** Validate credential submission for staff administration sign-in. */
class AdminLoginRequest extends AdminFormRequest
{
    /** Allow the public login route to validate credentials before authentication. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Credentials used to issue a scoped Sanctum token. */
    public function rules(): array
    {
        return ['identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:255']];
    }
}
