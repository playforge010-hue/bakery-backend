<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'raw_response_json' => 'array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
