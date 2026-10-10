<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number', 'user_id', 'order_id', 'subject', 'status', 'priority',
        'assigned_to_user_id', 'category', 'client_message_id',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
