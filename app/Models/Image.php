<?php

namespace App\Models;

use App\Enums\ImageStatus;
use App\Enums\ImageType;
use App\Traits\Service\SavesImagesToS3;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends Model
{
    use HasFactory, HasUlids, SavesImagesToS3, SoftDeletes;

    protected $fillable = [
        'book_id',
        'chapter_id',
        'character_id',
        'user_id',
        'profile_id',
        'type',
        'image_url',
        'prompt',
        'error',
        'status',
        'paragraph_index',
        'aspect_ratio',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'full_url',
    ];

    protected function casts(): array
    {
        return [
            'type' => ImageType::class,
            'status' => ImageStatus::class,
            'paragraph_index' => 'integer',
        ];
    }

    /**
     * Get the full CloudFront URL for the image.
     */
    public function getFullUrlAttribute(): ?string
    {
        if (! $this->image_url) {
            return null;
        }

        if (str_starts_with($this->image_url, 'http')) {
            return $this->image_url;
        }

        return $this->getCloudFrontImageUrl($this->image_url);
    }

    /**
     * Check if the image is ready to display.
     */
    public function getIsReadyAttribute(): bool
    {
        return $this->status === ImageStatus::Complete && $this->image_url !== null;
    }

    /**
     * Check if the image is currently being generated.
     */
    public function getIsProcessingAttribute(): bool
    {
        return in_array($this->status, [ImageStatus::Pending, ImageStatus::Processing]);
    }

    /**
     * Check if the image generation failed.
     */
    public function getHasErrorAttribute(): bool
    {
        return $this->status === ImageStatus::Error;
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Scope to get images by type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, ImageType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get images with a specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, ImageStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending or processing images.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [ImageStatus::Pending, ImageStatus::Processing]);
    }

    /**
     * Scope to get completed images.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', ImageStatus::Complete);
    }
}
