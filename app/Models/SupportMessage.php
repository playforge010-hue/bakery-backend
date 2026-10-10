<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SupportMessage extends Model
{
    public const CUSTOMER = 'customer';

    public const ADMIN = 'admin';

    protected $fillable = [
        'support_ticket_id', 'sender_user_id', 'sender_type', 'message',
        'attachment_path', 'client_message_id', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportMessageAttachment::class);
    }

    /** Preserve frontend-safe URLs for attachments created before live chat. */
    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path
            ? Storage::disk(config('imagepath.disk', 'public'))->url($this->attachment_path)
            : null;
    }
}
