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
        'profile_image_prompt',
        'age_group',
        'is_default',
        'themes',
        'active_theme_id',
        'background_image',
        'moderation_thresholds',
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
        'effective_moderation_thresholds',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'themes' => 'array',
            'moderation_thresholds' => 'array',
        ];
    }

    /**
     * Default moderation thresholds by age group.
     * Lower values = more restrictive (blocks content more easily).
     * Higher values = more permissive (allows more content through).
     *
     * @var array<string, array<string, float>>
     */
    public const AGE_GROUP_MODERATION_DEFAULTS = [
        '8' => [ // Kids (7-10) - Most restrictive
            'sexual' => 0.01,
            'sexual/minors' => 0.01,
            'harassment' => 0.3,
            'harassment/threatening' => 0.2,
            'hate' => 0.2,
            'hate/threatening' => 0.1,
            'illicit' => 0.2,
            'illicit/violent' => 0.1,
            'self-harm' => 0.1,
            'self-harm/intent' => 0.1,
            'self-harm/instructions' => 0.1,
            'violence' => 0.3,
            'violence/graphic' => 0.1,
        ],
        '12' => [ // Pre-Teen (11-13) - Moderately restrictive
            'sexual' => 0.05,
            'sexual/minors' => 0.01,
            'harassment' => 0.4,
            'harassment/threatening' => 0.3,
            'hate' => 0.3,
            'hate/threatening' => 0.2,
            'illicit' => 0.3,
            'illicit/violent' => 0.2,
            'self-harm' => 0.2,
            'self-harm/intent' => 0.2,
            'self-harm/instructions' => 0.1,
            'violence' => 0.5,
            'violence/graphic' => 0.2,
        ],
        '16' => [ // Teen (14-17) - Moderate
            'sexual' => 0.2,
            'sexual/minors' => 0.01,
            'harassment' => 0.5,
            'harassment/threatening' => 0.4,
            'hate' => 0.4,
            'hate/threatening' => 0.3,
            'illicit' => 0.4,
            'illicit/violent' => 0.3,
            'self-harm' => 0.3,
            'self-harm/intent' => 0.3,
            'self-harm/instructions' => 0.2,
            'violence' => 0.7,
            'violence/graphic' => 0.4,
        ],
        '18' => [ // Adult (18+) - Least restrictive
            'sexual' => 0.5,
            'sexual/minors' => 0.01, // Always very restrictive
            'harassment' => 0.7,
            'harassment/threatening' => 0.6,
            'hate' => 0.6,
            'hate/threatening' => 0.5,
            'illicit' => 0.6,
            'illicit/violent' => 0.5,
            'self-harm' => 0.5,
            'self-harm/intent' => 0.5,
            'self-harm/instructions' => 0.4,
            'violence' => 0.9,
            'violence/graphic' => 0.7,
        ],
    ];

    /**
     * Category labels for display in the UI.
     *
     * @var array<string, array{label: string, description: string, icon: string}>
     */
    public const MODERATION_CATEGORIES = [
        'sexual' => [
            'label' => 'Sexual Content',
            'description' => 'Adult or suggestive content',
            'icon' => 'heart',
        ],
        'sexual/minors' => [
            'label' => 'Sexual (Minors)',
            'description' => 'Content involving minors - always strictly filtered',
            'icon' => 'shield-alert',
        ],
        'harassment' => [
            'label' => 'Harassment',
            'description' => 'Bullying, insults, or hostile behavior',
            'icon' => 'message-circle-warning',
        ],
        'harassment/threatening' => [
            'label' => 'Threatening Harassment',
            'description' => 'Harassment with threats of harm',
            'icon' => 'alert-triangle',
        ],
        'hate' => [
            'label' => 'Hate Speech',
            'description' => 'Content targeting protected groups',
            'icon' => 'ban',
        ],
        'hate/threatening' => [
            'label' => 'Threatening Hate',
            'description' => 'Hate speech with threats of violence',
            'icon' => 'flame',
        ],
        'illicit' => [
            'label' => 'Illicit Content',
            'description' => 'Illegal activities or substance abuse',
            'icon' => 'alert-octagon',
        ],
        'illicit/violent' => [
            'label' => 'Violent Illicit',
            'description' => 'Illegal activities involving violence',
            'icon' => 'skull',
        ],
        'self-harm' => [
            'label' => 'Self-Harm',
            'description' => 'Content depicting self-harm',
            'icon' => 'heart-crack',
        ],
        'self-harm/intent' => [
            'label' => 'Self-Harm Intent',
            'description' => 'Expression of intent to self-harm',
            'icon' => 'circle-alert',
        ],
        'self-harm/instructions' => [
            'label' => 'Self-Harm Instructions',
            'description' => 'Instructions for self-harm',
            'icon' => 'file-warning',
        ],
        'violence' => [
            'label' => 'Violence',
            'description' => 'Physical violence or fighting',
            'icon' => 'swords',
        ],
        'violence/graphic' => [
            'label' => 'Graphic Violence',
            'description' => 'Detailed or gory depictions of violence',
            'icon' => 'droplets',
        ],
    ];

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
     * Get the effective moderation thresholds (user overrides + age defaults).
     *
     * @return array<string, float>
     */
    protected function effectiveModerationThresholds(): Attribute
    {
        return Attribute::make(
            get: function () {
                $defaults = self::AGE_GROUP_MODERATION_DEFAULTS[$this->age_group]
                    ?? self::AGE_GROUP_MODERATION_DEFAULTS['18'];

                $customThresholds = $this->moderation_thresholds ?? [];

                return array_merge($defaults, $customThresholds);
            },
        );
    }

    /**
     * Get the default moderation thresholds for this profile's age group.
     *
     * @return array<string, float>
     */
    public function getDefaultModerationThresholds(): array
    {
        return self::AGE_GROUP_MODERATION_DEFAULTS[$this->age_group]
            ?? self::AGE_GROUP_MODERATION_DEFAULTS['18'];
    }

    /**
     * Check if a category score exceeds the profile's threshold.
     */
    public function exceedsModerationThreshold(string $category, float $score): bool
    {
        $thresholds = $this->effective_moderation_thresholds;

        return $score >= ($thresholds[$category] ?? 0.5);
    }

    /**
     * Get the active theme for this profile.
     *
     * @return array{id: string, name: string, background_color: string, text_color: string, background_image: string|null, background_description: string|null}|null
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
     * @param  array{id: string, name: string, background_color: string, text_color: string, background_image: string|null, background_description: string|null}  $theme
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
     * @param  array{id: string, name: string, background_color: string, text_color: string, background_image: string|null, background_description: string|null}  $theme
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
     * Get all conversations for this profile.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
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
