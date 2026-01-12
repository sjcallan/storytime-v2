<?php

namespace App\Models;

use App\Enums\ImageType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chapter extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'title',
        'user_id',
        'profile_id',
        'book_id',
        'body',
        'summary',
        'user_prompt',
        'error',
        'final_chapter',
        'sort',
        'cta',
        'cta_total_cost',
        'image_prompt',
        'image',
        'header_image_id',
        'inline_images',
        'book_summary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'final_chapter' => 'boolean',
            'cta_total_cost' => 'decimal:8',
            'inline_images' => 'array',
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

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'chapter_character')
            ->withPivot(['inner_thoughts', 'experience', 'goals', 'personal_motivations'])
            ->withTimestamps();
    }

    public function chapterCharacters(): HasMany
    {
        return $this->hasMany(ChapterCharacter::class);
    }

    public function readingLogs(): HasMany
    {
        return $this->hasMany(ReadingLog::class);
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(RequestLog::class);
    }

    /**
     * Get the header image for this chapter.
     */
    public function headerImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'header_image_id');
    }

    /**
     * Get all inline images for this chapter.
     */
    public function inlineImages(): HasMany
    {
        return $this->hasMany(Image::class)
            ->where('type', ImageType::ChapterInline)
            ->orderBy('paragraph_index');
    }

    /**
     * Get all images associated with this chapter.
     */
    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Get the header image URL (from new Image model or legacy field).
     */
    public function getHeaderImageUrlAttribute(): ?string
    {
        // First check the new Image relationship
        if ($this->headerImage && $this->headerImage->image_url) {
            return $this->headerImage->full_url;
        }

        // Fall back to legacy image field
        return $this->image;
    }

    /**
     * Get inline images as array (from new Image model or legacy field).
     */
    public function getInlineImagesArrayAttribute(): array
    {
        // First check the new Image relationship
        $newImages = $this->inlineImages()->get();

        if ($newImages->isNotEmpty()) {
            return $newImages->map(function ($image) {
                return [
                    'id' => $image->id,
                    'paragraph_index' => $image->paragraph_index,
                    'url' => $image->full_url,
                    'prompt' => $image->prompt,
                    'status' => $image->status->value,
                    'error' => $image->error,
                ];
            })->toArray();
        }

        // Fall back to legacy inline_images field
        return $this->inline_images ?? [];
    }
}
