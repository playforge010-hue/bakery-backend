<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\ImagePath;
use Illuminate\Support\Str;

/** A scheduled homepage promotion and its configured destination. */
class Banner extends Model
{
    protected $fillable = [
        'title_translations',
        'subtitle_translations',
        'image_path',
        'link_type',
        'link_value',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'title_translations' => 'array',
            'subtitle_translations' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Restrict homepage banner queries to enabled records inside their display window. */
    public function scopeCurrentlyActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($builder) => $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Resolve an external seed image or a relative path on the configured image disk. */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (Str::startsWith($this->image_path, ['https://', 'http://'])) {
            return $this->image_path;
        }

        return ImagePath::url($this->image_path);
    }
}
