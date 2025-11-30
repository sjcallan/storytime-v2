<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'title',
        'cover_image',
        'cover_image_prompt',
        'status',
        'age_level',
        'genre',
        'user_id',
        'profile_id',
        'plot',
        'summary',
        'type',
        'author',
        'last_opened_date',
        'is_published',
        'user_characters',
        'scene',
        'published_at',
        'additional_instructions',
        'first_chapter_prompt',
    ];

    protected function casts(): array
    {
        return [
            'last_opened_date' => 'datetime',
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    public function readingLogs(): HasMany
    {
        return $this->hasMany(ReadingLog::class);
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(RequestLog::class);
    }
}
