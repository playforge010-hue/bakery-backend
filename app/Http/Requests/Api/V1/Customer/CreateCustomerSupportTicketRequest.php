<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\Support\SendSupportMessageRequest;
use App\Services\Support\SupportAccess;
use Illuminate\Validation\Rule;

/** Validate a customer support message and its optional related order number. */
class CreateCustomerSupportTicketRequest extends SendSupportMessageRequest
{
    /** Ticket creation is available only through the authenticated customer route. */
    public function authorize(): bool
    {
        return $this->user() !== null && SupportAccess::isCustomer($this->user());
    }

    /** Restrict ticket fields to the customer support form contract. */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['ticket_number']);

        return $rules + [
            'subject' => ['required', 'string', 'max:190', 'not_regex:/^\s*$/u'],
            'category' => ['required', 'in:order,payment,delivery,customization,other'],
            'order_number' => ['nullable', 'string', 'max:100', Rule::exists('orders', 'order_number')
                ->where('user_id', $this->user()?->id)],
        ];
    }
}
