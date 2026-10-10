<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessHoliday extends Model
{
    protected $fillable = ['holiday_date', 'title', 'opens_at', 'closes_at', 'is_closed', 'note'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date', 'is_closed' => 'boolean'];
    }
}
