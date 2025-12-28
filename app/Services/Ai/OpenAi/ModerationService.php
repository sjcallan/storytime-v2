<?php

namespace App\Services\Ai\OpenAi;

use App\Models\Moderation;
use App\Models\Profile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModerationService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected string $model;

    protected ?array $lastResponse = null;

    protected ?Moderation $lastModeration = null;

    public function __construct()
    {
        $this->apiKey = config('ai.providers.openai.api_key') ?? config('services.openai.api_key');
        $this->baseUrl = config('ai.providers.openai.base_url') ?? 'https://api.openai.com/v1';
        $this->model = config('ai.moderation.model', 'omni-moderation-latest');
    }

    /**
     * Check if moderation is enabled.
     */
    public function isEnabled(): bool
    {
        return config('ai.moderation.enabled', false) === true;
    }

    /**
     * Get the profile-specific thresholds or fall back to config defaults.
     *
     * @return array<string, float>
     */
    protected function getThresholdsForProfile(?string $profileId): array
    {
        if ($profileId) {
            $profile = Profile::find($profileId);
            if ($profile) {
                return $profile->effective_moderation_thresholds;
            }
        }

        $defaultThreshold = config('ai.moderation.min_threshold', 0.5);

        return array_fill_keys(array_keys(Profile::MODERATION_CATEGORIES), $defaultThreshold);
    }

    /**
     * Moderate text content and record the result.
     *
     * @param  array<string, mixed>  $context  Optional context like user_id, profile_id, source
     */
    public function moderate(string $input, array $context = []): ModerationResult
    {
        if (! $this->isEnabled()) {
            return new ModerationResult(
                flagged: false,
                categories: [],
                categoryScores: [],
                moderationId: null,
                model: null,
            );
        }

        $response = $this->callApi($input);

        if (! $response) {
            Log::warning('Moderation API call failed, allowing content by default', [
                'input_length' => strlen($input),
            ]);

            return new ModerationResult(
                flagged: false,
                categories: [],
                categoryScores: [],
                moderationId: null,
                model: null,
            );
        }

        $this->lastResponse = $response;

        $result = $response['results'][0] ?? [];
        $flagged = $result['flagged'] ?? false;
        $categories = $result['categories'] ?? [];
        $categoryScores = $result['category_scores'] ?? [];

        $profileId = $context['profile_id'] ?? session('current_profile_id');
        $thresholds = $this->getThresholdsForProfile($profileId);

        $categoriesAboveThreshold = collect($categories)
            ->filter(function ($isFlagged, $category) use ($categoryScores, $thresholds) {
                $score = $categoryScores[$category] ?? 0;
                $threshold = $thresholds[$category] ?? 0.5;

                return $score >= $threshold;
            })
            ->toArray();

        $anyFlaggedCategory = ! empty($categoriesAboveThreshold);
        $openAiFlagged = $flagged || collect($categories)->contains(true);
        $isFlagged = $anyFlaggedCategory || $openAiFlagged;

        if ($openAiFlagged && ! $anyFlaggedCategory) {
            $ignoredCategories = collect($categories)
                ->filter(fn ($v) => $v === true)
                ->keys()
                ->mapWithKeys(fn ($cat) => [$cat => [
                    'score' => $categoryScores[$cat] ?? 0,
                    'threshold' => $thresholds[$cat] ?? 0.5,
                ]])
                ->toArray();

            Log::info('[ModerationService] Ignoring low-confidence moderation flag', [
                'source' => $context['source'] ?? 'unknown',
                'profile_id' => $profileId,
                'ignored_categories' => $ignoredCategories,
                'input_preview' => mb_substr($input, 0, 200),
            ]);
        }

        if ($isFlagged) {
            $flaggedCategories = collect($categoriesAboveThreshold)
                ->keys()
                ->toArray();

            $topScores = collect($categoryScores)
                ->sortDesc()
                ->take(5)
                ->mapWithKeys(fn ($score, $cat) => [$cat => [
                    'score' => $score,
                    'threshold' => $thresholds[$cat] ?? 0.5,
                ]])
                ->toArray();

            Log::warning('[ModerationService] Content flagged for moderation', [
                'source' => $context['source'] ?? 'unknown',
                'user_id' => $context['user_id'] ?? auth()->id(),
                'profile_id' => $profileId,
                'flagged_categories' => $flaggedCategories,
                'openai_flagged' => $openAiFlagged,
                'top_scores' => $topScores,
                'input_length' => strlen($input),
                'input_preview' => mb_substr($input, 0, 500).(strlen($input) > 500 ? '...' : ''),
                'moderation_id' => $response['id'] ?? null,
            ]);
        }

        $filteredCategories = collect($categoryScores)
            ->map(function ($score, $category) use ($thresholds, $categories) {
                $threshold = $thresholds[$category] ?? 0.5;
                $openAiFlag = $categories[$category] ?? false;

                return ($score >= $threshold) || ($openAiFlag === true);
            })
            ->toArray();

        $this->lastModeration = $this->recordModeration(
            input: $input,
            response: $response,
            flagged: $isFlagged,
            categories: $filteredCategories,
            categoryScores: $categoryScores,
            moderationId: $response['id'] ?? null,
            model: $response['model'] ?? $this->model,
            context: $context,
        );

        return new ModerationResult(
            flagged: $isFlagged,
            categories: $filteredCategories,
            categoryScores: $categoryScores,
            moderationId: $response['id'] ?? null,
            model: $response['model'] ?? $this->model,
            moderation: $this->lastModeration,
        );
    }

    /**
     * Call the OpenAI moderation API.
     *
     * @return array<string, mixed>|null
     */
    protected function callApi(string $input): ?array
    {
        $url = "{$this->baseUrl}/moderations";

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post($url, [
                    'model' => $this->model,
                    'input' => $input,
                ]);

            if ($response->failed()) {
                Log::error('OpenAI Moderation API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('OpenAI Moderation API exception', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Record the moderation result to the database.
     *
     * @param  array<string, mixed>  $response
     * @param  array<string, bool>  $categories
     * @param  array<string, float>  $categoryScores
     * @param  array<string, mixed>  $context
     */
    protected function recordModeration(
        string $input,
        array $response,
        bool $flagged,
        array $categories,
        array $categoryScores,
        ?string $moderationId,
        ?string $model,
        array $context,
    ): Moderation {
        return Moderation::create([
            'user_id' => $context['user_id'] ?? auth()->id(),
            'profile_id' => $context['profile_id'] ?? session('current_profile_id'),
            'input' => $input,
            'response' => $response,
            'flagged' => $flagged,
            'categories' => $categories,
            'category_scores' => $categoryScores,
            'moderation_id' => $moderationId,
            'model' => $model,
            'source' => $context['source'] ?? null,
        ]);
    }

    /**
     * Get the last API response.
     *
     * @return array<string, mixed>|null
     */
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }

    /**
     * Get the last recorded moderation.
     */
    public function getLastModeration(): ?Moderation
    {
        return $this->lastModeration;
    }
}
