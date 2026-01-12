<?php

namespace App\Services\Replicate;

use App\Jobs\TrackImageRequestJob;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReplicateApiService
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.replicate.com/v1';

    protected string $defaultModel = 'black-forest-labs/flux-2-max';

    protected string $kreaModel = 'black-forest-labs/flux-krea-dev';

    protected ?string $customModelVersion;

    protected ?string $customModelLora;

    protected bool $useCustomModel;

    protected float $customModelLoraScale;

    public function __construct()
    {
        $this->apiKey = config('services.replicate.api_key');
        $this->customModelVersion = config('services.replicate.custom_model_version');
        $this->customModelLora = config('services.replicate.custom_model_lora');
        $this->customModelLoraScale = config('services.replicate.custom_model_lora_scale');
        $this->useCustomModel = (bool) config('services.replicate.use_custom_model', false);
    }

    /**
     * Generate an image using the Flux 2 Pro model.
     *
     * @param  string  $prompt  The text prompt for image generation
     * @param  array<string>|null  $inputImages  Optional array of image URLs for image-to-image generation (max 8)
     * @param  string  $aspectRatio  The aspect ratio for the generated image (default: 16:9)
     * @param  array<string, mixed>|null  $trackingContext  Optional context for request logging
     * @return array{url: string|null, error: string|null}
     */
    public function generateImage(
        string $prompt,
        ?array $inputImages = null,
        string $aspectRatio = '16:9',
        ?array $trackingContext = null
    ): array {
        if ($this->useCustomModel) {
            return $this->generateImageWithCustomModel($prompt, $aspectRatio, [], $trackingContext);
        }

        $startTime = microtime(true);

        $input = [
            'prompt' => $prompt,
            'aspect_ratio' => $aspectRatio,
            'safety_tolerance' => 5,
        ];

        $inputImagesCount = 0;
        if ($inputImages !== null && count($inputImages) > 0) {
            $input['input_images'] = array_slice($inputImages, 0, 8);
            $inputImagesCount = count($input['input_images']);
            Log::info('Replicate API: Adding character images to request', [
                'input_images_count' => $inputImagesCount,
                'input_images' => $input['input_images'],
            ]);
        }

        Log::debug('Replicate API: Making request', [
            'model' => $this->defaultModel,
            'prompt_length' => strlen($prompt),
            'prompt_preview' => substr($prompt, 0, 150),
            'aspect_ratio' => $aspectRatio,
            'has_input_images' => isset($input['input_images']),
            'input_images_count' => $inputImagesCount,
        ]);

        $response = $this->makeRequestWithRetry($this->defaultModel, $input);
        $responseTime = microtime(true) - $startTime;

        if ($response->failed()) {
            Log::error('Replicate API error', [
                'model' => $this->defaultModel,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $result = [
                'url' => null,
                'error' => $response->json('detail') ?? 'Failed to generate image',
            ];

            $this->trackRequest(
                $this->defaultModel,
                $trackingContext,
                $result,
                $prompt,
                $inputImagesCount,
                0,
                $responseTime
            );

            return $result;
        }

        $data = $response->json();

        $result = [
            'url' => $data['output'] ?? null,
            'error' => null,
        ];

        $this->trackRequest(
            $this->defaultModel,
            $trackingContext,
            $result,
            $prompt,
            $inputImagesCount,
            $result['url'] ? 1 : 0,
            $responseTime
        );

        return $result;
    }

    /**
     * Generate an image using the Flux Krea Dev model with enhanced parameters.
     * Best for realistic, high-quality photographic images.
     *
     * @param  string  $prompt  The text prompt for image generation
     * @param  string  $aspectRatio  The aspect ratio for the generated image (default: 1:1)
     * @param  array<string, mixed>|null  $trackingContext  Optional context for request logging
     * @return array{url: string|null, error: string|null}
     */
    public function generateImageWithKrea(
        string $prompt,
        string $aspectRatio = '1:1',
        ?array $trackingContext = null
    ): array {
        if ($this->useCustomModel) {
            return $this->generateImageWithCustomModel($prompt, $aspectRatio, [], $trackingContext);
        }

        $startTime = microtime(true);

        $input = [
            'prompt' => $prompt,
            'go_fast' => true,
            'guidance' => 2.5,
            'megapixels' => '1',
            'num_outputs' => 1,
            'aspect_ratio' => $aspectRatio,
            'disable_safety_checker' => true,
            'output_format' => 'webp',
            'output_quality' => 95,
            'prompt_strength' => 0.8,
            'num_inference_steps' => 28,
        ];

        Log::debug('Replicate API: Making request with Krea model', [
            'model' => $this->kreaModel,
            'prompt_length' => strlen($prompt),
            'prompt_preview' => substr($prompt, 0, 150),
            'aspect_ratio' => $aspectRatio,
            'guidance' => $input['guidance'],
        ]);

        $response = $this->makeRequestWithRetry($this->kreaModel, $input);
        $responseTime = microtime(true) - $startTime;

        if ($response->failed()) {
            Log::error('Replicate API error with Krea model', [
                'model' => $this->kreaModel,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $result = [
                'url' => null,
                'error' => $response->json('detail') ?? 'Failed to generate image',
            ];

            $this->trackRequest(
                $this->kreaModel,
                $trackingContext,
                $result,
                $prompt,
                0,
                0,
                $responseTime
            );

            return $result;
        }

        $data = $response->json();
        $output = $data['output'] ?? null;

        if (is_array($output) && count($output) > 0) {
            $url = $output[0];
        } else {
            $url = $output;
        }

        Log::debug('Replicate API: Krea model response processed', [
            'output_is_array' => is_array($output),
            'output_count' => is_array($output) ? count($output) : 0,
            'extracted_url' => $url,
        ]);

        $result = [
            'url' => $url,
            'error' => null,
        ];

        $this->trackRequest(
            $this->kreaModel,
            $trackingContext,
            $result,
            $prompt,
            0,
            $url ? 1 : 0,
            $responseTime
        );

        return $result;
    }

    /**
     * Generate an image using a custom model/version with LoRA training.
     * Best for consistent character generation with trained models.
     *
     * @param  string  $prompt  The text prompt for image generation
     * @param  string  $aspectRatio  The aspect ratio for the generated image (default: 1:1)
     * @param  array<string, mixed>  $customParams  Optional custom parameters to override defaults
     * @param  array<string, mixed>|null  $trackingContext  Optional context for request logging
     * @return array{url: string|null, error: string|null}
     */
    public function generateImageWithCustomModel(
        string $prompt,
        string $aspectRatio = '1:1',
        array $customParams = [],
        ?array $trackingContext = null
    ): array {
        $startTime = microtime(true);

        $defaultParams = [
            'prompt' => $prompt,
            'model' => 'dev',
            'go_fast' => false,
            'lora_scale' => 1,
            'megapixels' => '1',
            'num_outputs' => 1,
            'aspect_ratio' => $aspectRatio,
            'output_format' => 'webp',
            'guidance_scale' => 2,
            'output_quality' => 80,
            'prompt_strength' => 0.8,
            'extra_lora_scale' => $this->customModelLoraScale,
            'num_inference_steps' => 30,
            'disable_safety_checker' => true,
        ];

        if (! empty($this->customModelLora)) {
            $defaultParams['extra_lora'] = $this->customModelLora;
        }

        $input = array_merge($defaultParams, $customParams);

        Log::debug('Replicate API: Making request with custom model', [
            'model_version' => $this->customModelVersion,
            'prompt_length' => strlen($prompt),
            'prompt_preview' => substr($prompt, 0, 150),
            'aspect_ratio' => $aspectRatio,
            'guidance_scale' => $input['guidance_scale'],
            'lora_scale' => $input['lora_scale'],
            'extra_lora' => $input['extra_lora'] ?? null,
            'extra_lora_scale' => $input['extra_lora_scale'],
        ]);

        $response = $this->makeCustomModelRequestWithRetry($input);
        $responseTime = microtime(true) - $startTime;

        $modelName = 'custom/'.$this->customModelVersion;

        if ($response->failed()) {
            Log::error('Replicate API error with custom model', [
                'model_version' => $this->customModelVersion,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $result = [
                'url' => null,
                'error' => $response->json('detail') ?? 'Failed to generate image',
            ];

            $this->trackRequest(
                $modelName,
                $trackingContext,
                $result,
                $prompt,
                0,
                0,
                $responseTime
            );

            return $result;
        }

        $data = $response->json();
        $output = $data['output'] ?? null;

        if (is_array($output) && count($output) > 0) {
            $url = $output[0];
        } else {
            $url = $output;
        }

        Log::debug('Replicate API: Custom model response processed', [
            'output_is_array' => is_array($output),
            'output_count' => is_array($output) ? count($output) : 0,
            'extracted_url' => $url,
        ]);

        $result = [
            'url' => $url,
            'error' => null,
        ];

        $this->trackRequest(
            $modelName,
            $trackingContext,
            $result,
            $prompt,
            0,
            $url ? 1 : 0,
            $responseTime
        );

        return $result;
    }

    /**
     * Track the image generation request for logging and cost tracking.
     *
     * @param  array<string, mixed>|null  $context  Tracking context with user_id, book_id, etc.
     * @param  array{url: string|null, error: string|null}  $response
     */
    protected function trackRequest(
        string $model,
        ?array $context,
        array $response,
        string $prompt,
        int $inputImagesCount,
        int $outputImagesCount,
        float $responseTime
    ): void {
        if ($context === null) {
            return;
        }

        TrackImageRequestJob::dispatch(
            model: $model,
            itemType: $context['item_type'] ?? 'image_generation',
            response: $response,
            prompt: $prompt,
            inputImagesCount: $inputImagesCount,
            outputImagesCount: $outputImagesCount,
            responseTime: $responseTime,
            userId: $context['user_id'] ?? null,
            profileId: $context['profile_id'] ?? null,
            bookId: $context['book_id'] ?? null,
            chapterId: $context['chapter_id'] ?? null,
            characterId: $context['character_id'] ?? null,
        );
    }

    /**
     * Make a request to the Replicate API with retry logic for rate limiting.
     *
     * @param  string  $model  The model identifier to use
     * @param  array<string, mixed>  $input
     */
    protected function makeRequestWithRetry(string $model, array $input, int $maxRetries = 3): Response
    {
        $attempt = 0;

        while ($attempt <= $maxRetries) {
            $response = $this->makeRequest($model, $input);

            if ($response->status() === 429) {
                $attempt++;

                if ($attempt > $maxRetries) {
                    Log::warning('Replicate API: Max retries reached for rate limit', [
                        'model' => $model,
                    ]);

                    return $response;
                }

                $waitTime = $this->extractWaitTime($response->json('detail', ''));
                $waitSeconds = max($waitTime + 1, 5);

                Log::info('Replicate API: Rate limited, waiting before retry', [
                    'model' => $model,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'wait_seconds' => $waitSeconds,
                ]);

                sleep($waitSeconds);

                continue;
            }

            return $response;
        }

        return $this->makeRequest($model, $input);
    }

    /**
     * Make a request to the Replicate API with retry logic for custom model versions.
     *
     * @param  array<string, mixed>  $input
     */
    protected function makeCustomModelRequestWithRetry(array $input, int $maxRetries = 3): Response
    {
        $attempt = 0;

        while ($attempt <= $maxRetries) {
            $response = $this->makeCustomModelRequest($input);

            if ($response->status() === 429) {
                $attempt++;

                if ($attempt > $maxRetries) {
                    Log::warning('Replicate API: Max retries reached for rate limit', [
                        'model_version' => $this->customModelVersion,
                    ]);

                    return $response;
                }

                $waitTime = $this->extractWaitTime($response->json('detail', ''));
                $waitSeconds = max($waitTime + 1, 5);

                Log::info('Replicate API: Rate limited, waiting before retry', [
                    'model_version' => $this->customModelVersion,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'wait_seconds' => $waitSeconds,
                ]);

                sleep($waitSeconds);

                continue;
            }

            return $response;
        }

        return $this->makeCustomModelRequest($input);
    }

    /**
     * Extract wait time from rate limit error message.
     */
    protected function extractWaitTime(string $errorMessage): int
    {
        if (preg_match('/resets in ~?(\d+)s/', $errorMessage, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/resets in ~?(\d+)\s*seconds?/', $errorMessage, $matches)) {
            return (int) $matches[1];
        }

        return 5;
    }

    /**
     * Make a request to the Replicate API.
     *
     * @param  string  $model  The model identifier to use
     * @param  array<string, mixed>  $input
     */
    protected function makeRequest(string $model, array $input): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'wait',
        ])->timeout(120)->post("{$this->baseUrl}/models/{$model}/predictions", [
            'input' => $input,
        ]);
    }

    /**
     * Make a request to the Replicate API using a custom model version.
     *
     * @param  array<string, mixed>  $input
     */
    protected function makeCustomModelRequest(array $input): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'wait',
        ])->timeout(120)->post("{$this->baseUrl}/predictions", [
            'version' => $this->customModelVersion,
            'input' => $input,
        ]);
    }
}
