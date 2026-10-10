<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'properties_json', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['properties_json' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
