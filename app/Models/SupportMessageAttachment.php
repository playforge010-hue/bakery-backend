<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Private support uploads expose an authorized API URL, never a storage path. */
class SupportMessageAttachment extends Model
{
    protected $fillable = ['support_message_id', 'disk', 'path', 'mime_type', 'size', 'width', 'height'];

    protected $hidden = ['disk', 'path'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }

    public function getImageUrlAttribute(): string
    {
        return url('/api/v1/support/get-attachment?attachment_id='.$this->id);
    }
}
