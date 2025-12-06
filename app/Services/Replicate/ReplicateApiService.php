<?php

namespace App\Services\Replicate;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReplicateApiService
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.replicate.com/v1';

    protected string $model = 'black-forest-labs/flux-2-pro';

    public function __construct()
    {
        $this->apiKey = config('services.replicate.api_key');
    }

    /**
     * Generate an image using the Flux 2 Pro model.
     *
     * @param  string  $prompt  The text prompt for image generation
     * @param  array<string>|null  $inputImages  Optional array of image URLs for image-to-image generation (max 8)
     * @param  string  $aspectRatio  The aspect ratio for the generated image (default: 16:9)
     * @return array{url: string|null, error: string|null}
     */
    public function generateImage(
        string $prompt,
        ?array $inputImages = null,
        string $aspectRatio = '16:9'
    ): array {
        $input = [
            'prompt' => $prompt,
            'aspect_ratio' => $aspectRatio,
            'safety_tolerance' => 5,
        ];

        if ($inputImages !== null && count($inputImages) > 0) {
            $input['input_images'] = array_slice($inputImages, 0, 8);
        }

        $response = $this->makeRequest($input);

        if ($response->failed()) {
            Log::error('Replicate API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'url' => null,
                'error' => $response->json('detail') ?? 'Failed to generate image',
            ];
        }

        $data = $response->json();

        return [
            'url' => $data['output'] ?? null,
            'error' => null,
        ];
    }

    /**
     * Make a request to the Replicate API.
     *
     * @param  array<string, mixed>  $input
     */
    protected function makeRequest(array $input): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'wait',
        ])->timeout(120)->post("{$this->baseUrl}/models/{$this->model}/predictions", [
            'input' => $input,
        ]);
    }
}
