<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Support\SupportConversationController;
use App\Http\Requests\Api\V1\Customer\CreateCustomerSupportTicketRequest;
use Illuminate\Http\JsonResponse;

/** Customer conversation endpoints; every history query is scoped to its owner. */
class CustomerSupportController extends SupportConversationController
{
    /** Create an authenticated request and initial text/images atomically with retry deduplication. */
    public function createSupportTicket(CreateCustomerSupportTicketRequest $request): JsonResponse
    {
        $result = $this->chat->createTicket($request->user(), $request->validated(), $request->file('images', []));

        return $this->successResponse('Support request created.', $result, [], $result['created'] ? 201 : 200);
    }
}
