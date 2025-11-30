<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Profile extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Age group options that match the story creation form.
     */
    public const AGE_GROUPS = [
        '8' => ['label' => 'Kids', 'range' => '7-10', 'emoji' => '👶'],
        '12' => ['label' => 'Pre-Teen', 'range' => '11-13', 'emoji' => '🧒'],
        '16' => ['label' => 'Teen', 'range' => '14-17', 'emoji' => '🧑'],
        '18' => ['label' => 'Adult', 'range' => '18+', 'emoji' => '🧑‍🦱'],
    ];

    protected $fillable = [
        'user_id',
        'name',
        'profile_image_path',
        'age_group',
        'is_default',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar',
        'age_group_label',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the URL to the profile's avatar image.
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profile_image_path
                ? Storage::disk('public')->url($this->profile_image_path)
                : null,
        );
    }

    /**
     * Get the human-readable age group label.
     */
    protected function ageGroupLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => self::AGE_GROUPS[$this->age_group]['label'] ?? 'Unknown',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    /**
     * Delete the profile's photo.
     */
    public function deleteProfileImage(): void
    {
        if ($this->profile_image_path) {
            Storage::disk('public')->delete($this->profile_image_path);
            $this->forceFill(['profile_image_path' => null])->save();
        }
    }
}
