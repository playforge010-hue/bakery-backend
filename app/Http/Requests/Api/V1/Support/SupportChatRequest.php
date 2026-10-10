<?php

namespace App\Http\Requests\Api\V1\Support;

use App\Enums\SupportTicketStatus;
use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Services\Support\SupportAccess;
use Illuminate\Validation\Rule;

/** Validate bounded chat reads, receipts, typing, status and attachment requests. */
class SupportChatRequest extends ApiFormRequest
{
    /** Require an active support account and token; services also scope ticket ownership. */
    public function authorize(): bool
    {
        return $this->user() !== null
            && (SupportAccess::isCustomer($this->user())
                || SupportAccess::isAdmin($this->user(), $this->isMethod('POST')));
    }

    /** Match validation to the business action without accepting unrelated write fields. */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        if ($action === 'getTicketList') {
            return [
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'between:1,50'],
                'search' => ['nullable', 'string', 'max:190'],
                'status' => ['nullable', Rule::enum(SupportTicketStatus::class)],
            ];
        }
        if ($action === 'getAttachment') {
            return ['attachment_id' => ['required', 'integer', 'min:1']];
        }

        $rules = ['ticket_number' => ['required', 'string', 'max:100']];
        if ($action === 'getTicketDetail') {
            $rules += [
                'before_id' => ['nullable', 'integer', 'min:1', 'prohibits:after_id'],
                'after_id' => ['nullable', 'integer', 'min:0', 'prohibits:before_id'],
                'limit' => ['sometimes', 'integer', 'between:1,100'],
            ];
        }
        if ($action === 'markMessagesRead') {
            $rules['up_to_message_id'] = ['required', 'integer', 'min:1'];
        }
        if ($action === 'sendTypingIndicator') {
            $rules['is_typing'] = ['required', 'boolean'];
        }
        if ($action === 'updateTicketStatus') {
            $rules['status'] = ['required', Rule::enum(SupportTicketStatus::class)];
        }

        return $rules;
    }
}
