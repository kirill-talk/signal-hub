<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Entry extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'user_id',
        'source_id',
        'topic_id',
        'external_id_hash',
        'title',
        'url',
        'author',
        'summary',
        'published_at',
        'is_read',
        'is_bookmarked',
        'is_noise',
        'is_highlighted',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_read' => 'boolean',
            'is_bookmarked' => 'boolean',
            'is_noise' => 'boolean',
            'is_highlighted' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'author' => $this->author,
        ];
    }
}