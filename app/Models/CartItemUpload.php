<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItemUpload extends Model
{
    protected $table = 'cart_item_uploads';

    protected $guarded = ['id'];

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class);
    }

    public function copyToOrderItem(OrderItem $orderItem): OrderItemUpload
    {
        return $orderItem->uploads()->create($this->only(['file_path', 'original_name', 'mime_type', 'file_size']));
    }
}
