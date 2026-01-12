<?php

namespace App\Models;

use App\Enums\ImageType;
use App\Traits\Service\SavesImagesToS3;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chapter extends Model
{
    use HasFactory, HasUlids, SavesImagesToS3, SoftDeletes;

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
        'header_image_id',
        'inline_images',
        'book_summary',
        'status',
    ];

    protected $appends = [
        'header_image_url',
        'image_prompt',
        'inline_images_array',
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
     * Get the header image URL from the Image model.
     */
    public function getHeaderImageUrlAttribute(): ?string
    {
        if ($this->headerImage && $this->headerImage->image_url) {
            return $this->headerImage->full_url;
        }

        return null;
    }

    /**
     * Get the header image prompt from the Image model.
     */
    public function getImagePromptAttribute(): ?string
    {
        if ($this->headerImage) {
            return $this->headerImage->prompt;
        }

        return null;
    }

    /**
     * Get inline images as array from the Image model.
     * Uses the inline_images JSON to get the correct (most recent) image IDs.
     */
    public function getInlineImagesArrayAttribute(): array
    {
        $inlineImagesJson = $this->inline_images ?? [];

        if (empty($inlineImagesJson)) {
            return [];
        }

        // Extract image IDs from the JSON references
        $imageIds = collect($inlineImagesJson)
            ->filter(fn ($item) => isset($item['image_id']))
            ->pluck('image_id')
            ->toArray();

        if (empty($imageIds)) {
            // Fallback: get all inline images for this chapter (legacy format)
            return $this->inlineImages()->get()->map(function ($image) {
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

        // Fetch the specific images by ID
        $images = Image::whereIn('id', $imageIds)->get()->keyBy('id');

        // Build the array in the order of the JSON, using paragraph_index from JSON
        return collect($inlineImagesJson)
            ->filter(fn ($item) => isset($item['image_id']))
            ->map(function ($item) use ($images) {
                $image = $images->get($item['image_id']);
                if (! $image) {
                    return null;
                }

                return [
                    'id' => $image->id,
                    'paragraph_index' => $item['paragraph_index'] ?? $image->paragraph_index,
                    'url' => $image->full_url,
                    'prompt' => $image->prompt,
                    'status' => $image->status->value,
                    'error' => $image->error,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }
}
