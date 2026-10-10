<?php

namespace App\Services\Support;

use App\Enums\SupportTicketStatus;
use App\Events\SupportChatChanged;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportMessageAttachment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** Own support authorization, cursor pagination, atomic uploads and realtime delivery. */
class SupportChatService
{
    public function __construct(private readonly SupportChatPresenter $presenter)
    {
    }

    public function listTickets(User $user, array $filters): array
    {
        $viewer = SupportAccess::senderType($user);
        $rows = $this->ticketQuery($user)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('ticket_number', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 30, ['*'], 'page', $filters['page'] ?? 1);

        return [
            'tickets' => $rows->map(fn ($ticket) => $this->presenter->ticket($ticket, $viewer))->all(),
            'meta' => [
                'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(),
                'total' => $rows->total(), 'per_page' => $rows->perPage(),
            ],
        ];
    }

    public function ticketDetail(User $user, array $data): array
    {
        $ticket = $this->findTicket($user, $data['ticket_number']);
        $query = $ticket->messages()->with('attachments');
        $after = isset($data['after_id']);
        if ($after) {
            $query->where('id', '>', $data['after_id'])->orderBy('id');
        } else {
            $query->when($data['before_id'] ?? null, fn ($query, $id) => $query->where('id', '<', $id))->orderByDesc('id');
        }
        $limit = $data['limit'] ?? 40;
        $messages = $query->limit($limit + 1)->get();
        $hasMore = $messages->count() > $limit;
        $messages = $messages->take($limit)->sortBy('id')->values();

        return [
            'ticket' => $this->presenter->ticket($ticket, SupportAccess::senderType($user)),
            'messages' => $messages->map(fn ($message) => $this->presenter->message($message))->all(),
            'meta' => [
                'has_more' => $hasMore, 'oldest_id' => $messages->first()?->id,
                'latest_id' => $messages->last()?->id, 'direction' => $after ? 'after' : 'before',
            ],
        ];
    }

    /** Serialize concurrent creates per customer; failed responses can be retried safely. */
    public function createTicket(User $user, array $data, array $images): array
    {
        SupportAccess::senderType($user, true);
        $stored = [];
        try {
            $result = DB::transaction(function () use ($user, $data, $images, &$stored) {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $ticket = SupportTicket::query()->where('user_id', $user->id)->where('client_message_id', $data['client_message_id'])->first();
                if ($ticket) {
                    $message = $ticket->messages()->with('attachments')->where('client_message_id', $data['client_message_id'])->firstOrFail();

                    return [$ticket, $message, false];
                }
                $order = empty($data['order_number']) ? null : Order::query()
                    ->where('user_id', $user->id)->where('order_number', $data['order_number'])->firstOrFail();
                $ticket = SupportTicket::query()->create([
                    'ticket_number' => 'SUP-'.Str::upper((string) Str::ulid()),
                    'user_id' => $user->id, 'order_id' => $order?->id,
                    'subject' => trim($data['subject']), 'category' => $data['category'],
                    'status' => SupportTicketStatus::Open->value, 'priority' => 'normal',
                    'client_message_id' => $data['client_message_id'],
                ]);
                $message = $this->persistMessage($ticket, $user, $data, $images, $stored);

                return [$ticket, $message, true];
            });
        } catch (Throwable $exception) {
            $this->removeUploads($stored);
            throw $exception;
        }

        return $this->messageResult($user, $result, 'created');
    }

    /** Lock the ticket before checking idempotency, state and writing all message images. */
    public function sendMessage(User $user, array $data, array $images): array
    {
        SupportAccess::senderType($user, true);
        $stored = [];
        try {
            $result = DB::transaction(function () use ($user, $data, $images, &$stored) {
                $ticket = $this->findTicket($user, $data['ticket_number'], true);
                $existing = $ticket->messages()->with('attachments')->where('sender_user_id', $user->id)
                    ->where('client_message_id', $data['client_message_id'])->first();
                if ($existing) {
                    return [$ticket, $existing, false];
                }
                if ($ticket->status === SupportTicketStatus::Closed->value) {
                    throw ValidationException::withMessages(['ticket_number' => ['This conversation is closed. Start a new request.']]);
                }
                $message = $this->persistMessage($ticket, $user, $data, $images, $stored);
                if ($ticket->status === SupportTicketStatus::Resolved->value) {
                    $ticket->status = SupportTicketStatus::Open->value;
                } elseif (SupportAccess::isAdmin($user) && $ticket->status === SupportTicketStatus::Open->value) {
                    $ticket->status = SupportTicketStatus::InProgress->value;
                }
                $ticket->updated_at = now();
                $ticket->save();

                return [$ticket, $message, true];
            });
        } catch (Throwable $exception) {
            $this->removeUploads($stored);
            throw $exception;
        }

        return $this->messageResult($user, $result, 'message');
    }

    public function markRead(User $user, array $data): array
    {
        $viewer = SupportAccess::senderType($user, true);
        $readAt = now();
        [$ticket, $changed] = DB::transaction(function () use ($user, $data, $viewer, $readAt) {
            $ticket = $this->findTicket($user, $data['ticket_number'], true);
            $ticket->messages()->whereKey($data['up_to_message_id'])->firstOrFail();
            $changed = $ticket->messages()->where('sender_type', '!=', $viewer)
                ->where('id', '<=', $data['up_to_message_id'])->whereNull('read_at')->update(['read_at' => $readAt]);

            return [$ticket, $changed];
        });
        $receipt = [
            'reader_type' => $viewer, 'up_to_message_id' => (int) $data['up_to_message_id'],
            'read_at' => $readAt->toIso8601String(),
        ];
        if ($changed) {
            $this->publish($ticket, 'read', ['receipt' => $receipt]);
        }

        return $receipt;
    }

    public function typing(User $user, array $data): void
    {
        $sender = SupportAccess::senderType($user, true);
        $ticket = $this->findTicket($user, $data['ticket_number']);
        $this->publish($ticket, 'typing', ['sender_type' => $sender, 'is_typing' => (bool) $data['is_typing']]);
    }

    public function updateStatus(User $user, array $data): array
    {
        abort_unless(SupportAccess::isAdmin($user, true), 403);
        $ticket = DB::transaction(function () use ($user, $data) {
            $ticket = $this->findTicket($user, $data['ticket_number'], true);
            $ticket->update(['status' => $data['status']]);

            return $ticket;
        });
        $this->publish($ticket, 'status');

        return $this->presenter->ticket($this->findTicket($user, $ticket->ticket_number), SupportMessage::ADMIN);
    }

    /** Serve images only after checking the parent ticket against the authenticated account. */
    public function attachment(User $user, int $id): SupportMessageAttachment
    {
        $attachment = SupportMessageAttachment::query()->with('message.ticket')->findOrFail($id);
        $this->findTicket($user, $attachment->message->ticket->ticket_number);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return $attachment;
    }

    private function ticketQuery(User $user): Builder
    {
        $viewer = SupportAccess::senderType($user);

        return SupportTicket::query()
            ->when($viewer === SupportMessage::CUSTOMER, fn ($query) => $query->where('user_id', $user->id))
            ->with(['customer:id,name', 'order:id,order_number', 'latestMessage'])
            ->withCount(['messages as '.$viewer.'_unread_count' => fn ($query) => $query
                ->where('sender_type', '!=', $viewer)->whereNull('read_at')]);
    }

    private function findTicket(User $user, string $number, bool $lock = false): SupportTicket
    {
        return $this->ticketQuery($user)->where('ticket_number', $number)
            ->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
    }

    /** @param array<int, UploadedFile> $images */
    private function persistMessage(SupportTicket $ticket, User $user, array $data, array $images, array &$stored): SupportMessage
    {
        $message = $ticket->messages()->create([
            'sender_user_id' => $user->id, 'sender_type' => SupportAccess::senderType($user, true),
            'message' => $data['message'] ?? '', 'client_message_id' => $data['client_message_id'],
        ]);
        $disk = config('imagepath.support.disk', 'local');
        $directory = config('imagepath.support.directory', 'support/messages').'/'.$ticket->ticket_number;
        foreach ($images as $image) {
            $dimensions = getimagesize($image->getRealPath());
            $path = $image->store($directory, $disk);
            if ($path === false) {
                throw new RuntimeException('The support image could not be stored. Please retry.');
            }
            $stored[] = [$disk, $path];
            $message->attachments()->create([
                'disk' => $disk, 'path' => $path, 'mime_type' => $image->getMimeType(),
                'size' => $image->getSize(), 'width' => $dimensions[0], 'height' => $dimensions[1],
            ]);
        }

        return $message->load('attachments');
    }

    private function messageResult(User $user, array $result, string $change): array
    {
        [$ticket, $message, $created] = $result;
        $payload = $this->presenter->message($message);
        if ($created) {
            $this->publish($ticket, $change, ['message' => $payload]);
        }

        return [
            'ticket' => $this->presenter->ticket($this->findTicket($user, $ticket->ticket_number), SupportAccess::senderType($user)),
            'message' => $payload, 'created' => $created,
        ];
    }

    /** Persisted HTTP success must survive a transient Reverb outage; clients reconcile history. */
    private function publish(SupportTicket $ticket, string $change, array $extra = []): void
    {
        $payload = ['change_type' => $change, 'ticket_number' => $ticket->ticket_number] + $extra;
        if ($change !== 'typing') {
            $ticket->load(['customer:id,name', 'order:id,order_number', 'latestMessage']);
            $payload['ticket'] = $this->presenter->ticket($ticket);
        }
        try {
            event(new SupportChatChanged($payload));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function removeUploads(array $stored): void
    {
        foreach ($stored as [$disk, $path]) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
