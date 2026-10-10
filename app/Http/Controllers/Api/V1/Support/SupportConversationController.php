<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\SendSupportMessageRequest;
use App\Http\Requests\Api\V1\Support\SupportChatRequest;
use App\Services\Support\SupportChatService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Thin shared conversation actions for authenticated customer and administration routes. */
abstract class SupportConversationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected readonly SupportChatService $chat)
    {
    }

    /** List scoped conversations using optional status/search and bounded page parameters. */
    public function getTicketList(SupportChatRequest $request): JsonResponse
    {
        $result = $this->chat->listTickets($request->user(), $request->validated());

        return $this->successResponse('Support conversations loaded.', $result['tickets'], $result['meta']);
    }

    /** Read one owned conversation and a bounded window selected by message cursor. */
    public function getTicketDetail(SupportChatRequest $request): JsonResponse
    {
        $result = $this->chat->ticketDetail($request->user(), $request->validated());

        return $this->successResponse('Support conversation loaded.', [
            'ticket' => $result['ticket'], 'messages' => $result['messages'],
        ], $result['meta']);
    }

    /** Send text and/or images with a client UUID that remains stable across retries. */
    public function replyTicket(SendSupportMessageRequest $request): JsonResponse
    {
        $result = $this->chat->sendMessage($request->user(), $request->validated(), $request->file('images', []));

        return $this->successResponse('Support message sent.', $result, [], $result['created'] ? 201 : 200);
    }

    /** Acknowledge opposite-side messages through a message actually loaded by this viewer. */
    public function markMessagesRead(SupportChatRequest $request): JsonResponse
    {
        return $this->successResponse('Support messages marked read.',
            $this->chat->markRead($request->user(), $request->validated()));
    }

    /** Broadcast a short-lived typing signal for an authorized ticket without database writes. */
    public function sendTypingIndicator(SupportChatRequest $request): JsonResponse
    {
        $this->chat->typing($request->user(), $request->validated());

        return $this->successResponse('Typing indicator sent.');
    }

    /** Stream a private image by attachment id after checking conversation access. */
    public function getAttachment(SupportChatRequest $request): StreamedResponse
    {
        $attachment = $this->chat->attachment($request->user(), $request->integer('attachment_id'));

        return Storage::disk($attachment->disk)->response($attachment->path, null, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
