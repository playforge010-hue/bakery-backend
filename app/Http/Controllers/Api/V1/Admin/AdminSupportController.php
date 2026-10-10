<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Support\SupportConversationController;
use App\Http\Requests\Api\V1\Support\SupportChatRequest;
use Illuminate\Http\JsonResponse;

/** Dedicated support inbox and conversation lifecycle endpoints for the bakery team. */
class AdminSupportController extends SupportConversationController
{
    /** Set a validated ticket status, serializing it against concurrent replies. */
    public function updateTicketStatus(SupportChatRequest $request): JsonResponse
    {
        return $this->successResponse('Support status updated.',
            $this->chat->updateStatus($request->user(), $request->validated()));
    }
}
