<?php

namespace App\Services\Ai\OpenAi;

use App\Models\Moderation;

class ModerationResult
{
    /**
     * @param  array<string, bool>  $categories
     * @param  array<string, float>  $categoryScores
     */
    public function __construct(
        public readonly bool $flagged,
        public readonly array $categories,
        public readonly array $categoryScores,
        public readonly ?string $moderationId,
        public readonly ?string $model,
        public readonly ?Moderation $moderation = null,
    ) {}

    /**
     * Check if the content passed moderation (not flagged).
     */
    public function passed(): bool
    {
        return ! $this->flagged;
    }

    /**
     * Check if the content failed moderation (is flagged).
     */
    public function failed(): bool
    {
        return $this->flagged;
    }

    /**
     * Get the list of flagged category names.
     *
     * @return array<string>
     */
    public function getFlaggedCategories(): array
    {
        return collect($this->categories)
            ->filter(fn ($value) => $value === true)
            ->keys()
            ->toArray();
    }

    /**
     * Get a human-readable message for the violation.
     */
    public function getViolationMessage(): string
    {
        $flaggedCategories = $this->getFlaggedCategories();

        if (empty($flaggedCategories)) {
            return 'Content was flagged for violating our safety policy.';
        }

        $categoryLabels = [
            'sexual' => 'sexual content',
            'sexual/minors' => 'sexual content involving minors',
            'harassment' => 'harassment',
            'harassment/threatening' => 'threatening harassment',
            'hate' => 'hate speech',
            'hate/threatening' => 'threatening hate speech',
            'illicit' => 'illicit content',
            'illicit/violent' => 'illicit violent content',
            'self-harm' => 'self-harm content',
            'self-harm/intent' => 'self-harm intent',
            'self-harm/instructions' => 'self-harm instructions',
            'violence' => 'violence',
            'violence/graphic' => 'graphic violence',
        ];

        $labels = collect($flaggedCategories)
            ->map(fn ($category) => $categoryLabels[$category] ?? $category)
            ->toArray();

        return 'Content violates our safety policy regarding: '.implode(', ', $labels).'.';
    }
}
