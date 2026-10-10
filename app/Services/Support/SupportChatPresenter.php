<?php

namespace App\Services\Support;

use App\Models\SupportMessage;
use App\Models\SupportTicket;

/** Explicit, stable support payloads shared by API responses and broadcasts. */
class SupportChatPresenter
{
    public function ticket(SupportTicket $ticket, ?string $viewer = null): array
    {
        return [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'status' => $ticket->status,
            'order_number' => $ticket->order?->order_number,
            'customer' => ['name' => $ticket->customer?->name ?? 'Customer'],
            'latest_message' => $ticket->latestMessage?->message,
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'unread_count' => $viewer === null ? null : (int) $ticket->getAttribute($viewer.'_unread_count'),
        ];
    }

    public function message(SupportMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'client_message_id' => $message->client_message_id,
            'sender_type' => $message->sender_type,
            'message' => $message->message,
            'created_at' => $message->created_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'attachment_url' => $message->attachment_url,
            'attachments' => $message->attachments->map(fn ($attachment) => [
                'id' => (int) $attachment->id,
                'image_url' => $attachment->image_url,
                'mime_type' => $attachment->mime_type,
                'size' => (int) $attachment->size,
                'width' => (int) $attachment->width,
                'height' => (int) $attachment->height,
            ])->all(),
        ];
    }
}
