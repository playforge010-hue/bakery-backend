<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Publish committed support changes to a conversation and the authorized admin inbox. */
class SupportChatChanged implements ShouldBroadcastNow
{
    public function __construct(private readonly array $payload)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('support.'.$this->payload['ticket_number'])];
        if ($this->payload['change_type'] !== 'typing') {
            $channels[] = new PrivateChannel('admin.support');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'support.changed';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
