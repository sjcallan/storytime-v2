<?php

namespace App\Models;

use App\Traits\Service\SavesImagesToS3;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, HasUlids, SavesImagesToS3, SoftDeletes;

    protected $fillable = [
        'title',
        'cover_video_prompt',
        'cover_video',
        'cover_video_status',
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

    protected $appends = [
        'cover_image_url',
        'cover_image_status',
    ];

    protected $with = [
        'coverImage',
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

    /**full_url
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
     * Get the cover image URL from the Image model.
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if ($this->coverImage && $this->coverImage->image_url) {
            return $this->coverImage->full_url;
        }

        return null;
    }

    /**
     * Get the cover image status from the Image model.
     */
    public function getCoverImageStatusAttribute(): ?string
    {
        if ($this->coverImage) {
            return $this->coverImage->status->value;
        }

        return null;
    }
}
