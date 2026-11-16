<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'topic_id',
        'name',
        'url',
        'adapter_type',
        'fetch_interval_minutes',
        'last_fetched_at',
        'etag',
        'last_modified',
        'status',
        'error_count',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'fetch_interval_minutes' => 'integer',
            'last_fetched_at' => 'datetime',
            'error_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function filterRules(): HasMany
    {
        return $this->hasMany(FilterRule::class);
    }
}