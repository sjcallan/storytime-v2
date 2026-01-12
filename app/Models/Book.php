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
        'cover_video_prompt',
        'cover_video',
        'cover_video_status',
        'cover_image_status',
        'cover_image_id',
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

    public function readingHistory(): HasMany
    {
        return $this->hasMany(ReadingHistory::class);
    }

    /**
     * Get the cover image for the book.
     */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'cover_image_id');
    }

    /**
     * Get all images associated with this book.
     */
    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Get the cover image URL (from new Image model or legacy field).
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        // First check the new Image relationship
        if ($this->coverImage && $this->coverImage->image_url) {
            return $this->coverImage->full_url;
        }

        // Fall back to legacy cover_image field
        return $this->cover_image;
    }
}
