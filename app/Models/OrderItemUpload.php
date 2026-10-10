<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class OrderItemUpload extends Model
{
    protected $table = 'order_item_uploads';

    protected $guarded = ['id'];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** Resolve reference photos through the same configured disk used for checkout uploads. */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->file_path) {
                return null;
            }

            return Storage::disk(config('imagepath.disk', 'public'))->url($this->file_path);
        });
    }
}
