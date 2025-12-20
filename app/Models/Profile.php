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
        'themes',
        'active_theme_id',
        'background_image',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar',
        'age_group_label',
        'active_theme',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'themes' => 'array',
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

    /**
     * Get the active theme for this profile.
     *
     * @return array{id: string, name: string, background_color: string, text_color: string}|null
     */
    protected function activeTheme(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->active_theme_id || ! $this->themes) {
                    return null;
                }

                return collect($this->themes)->firstWhere('id', $this->active_theme_id);
            },
        );
    }

    /**
     * Add a new theme to the profile's themes.
     *
     * @param  array{id: string, name: string, background_color: string, text_color: string}  $theme
     */
    public function addTheme(array $theme): void
    {
        $themes = $this->themes ?? [];
        $themes[] = $theme;
        $this->themes = $themes;
        $this->save();
    }

    /**
     * Update an existing theme.
     *
     * @param  array{id: string, name: string, background_color: string, text_color: string}  $theme
     */
    public function updateTheme(array $theme): void
    {
        $themes = $this->themes ?? [];
        $themes = collect($themes)->map(function ($t) use ($theme) {
            return $t['id'] === $theme['id'] ? $theme : $t;
        })->values()->all();
        $this->themes = $themes;
        $this->save();
    }

    /**
     * Delete a theme from the profile.
     */
    public function deleteTheme(string $themeId): void
    {
        $themes = $this->themes ?? [];
        $themes = collect($themes)->reject(fn ($t) => $t['id'] === $themeId)->values()->all();
        $this->themes = $themes;

        if ($this->active_theme_id === $themeId) {
            $this->active_theme_id = null;
        }

        $this->save();
    }

    /**
     * Set the active theme for this profile.
     */
    public function setActiveTheme(?string $themeId): void
    {
        $this->active_theme_id = $themeId;
        $this->save();
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
     * Get all reading history records for this profile.
     */
    public function readingHistory(): HasMany
    {
        return $this->hasMany(ReadingHistory::class);
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
