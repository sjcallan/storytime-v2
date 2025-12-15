<?php

namespace App\Services\Theme;

use App\Services\Ai\OpenAi\ApiService as OpenAiApiService;
use App\Services\Replicate\ReplicateApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackgroundImageService
{
    public function __construct(
        protected OpenAiApiService $openAi,
        protected ReplicateApiService $replicate
    ) {}

    /**
     * Generate a background image from a user's description.
     *
     * @return array{url: string|null, error: string|null}
     */
    public function generateBackgroundImage(string $userDescription): array
    {
        Log::info('BackgroundImageService: Starting background image generation', [
            'user_description' => $userDescription,
        ]);

        // Step 1: Refine the prompt using OpenAI
        $refinedPrompt = $this->refinePromptWithOpenAi($userDescription);

        if ($refinedPrompt === null) {
            return [
                'url' => null,
                'error' => 'Failed to refine the prompt. Please try again.',
            ];
        }

        Log::info('BackgroundImageService: Prompt refined', [
            'original' => $userDescription,
            'refined' => $refinedPrompt,
        ]);

        // Step 2: Generate the image using Replicate Flux 2
        $result = $this->replicate->generateImage(
            prompt: $refinedPrompt,
            inputImages: null,
            aspectRatio: '16:9'
        );

        if ($result['error'] !== null || $result['url'] === null) {
            Log::error('BackgroundImageService: Failed to generate image', [
                'error' => $result['error'],
            ]);

            return [
                'url' => null,
                'error' => $result['error'] ?? 'Failed to generate background image.',
            ];
        }

        Log::info('BackgroundImageService: Image generated', [
            'replicate_url' => $result['url'],
        ]);

        // Step 3: Download and save to storage
        $savedPath = $this->saveImageToStorage($result['url']);

        if ($savedPath === null) {
            return [
                'url' => null,
                'error' => 'Failed to save the generated image.',
            ];
        }

        Log::info('BackgroundImageService: Image saved', [
            'path' => $savedPath,
        ]);

        return [
            'url' => $savedPath,
            'error' => null,
        ];
    }

    /**
     * Refine the user's prompt using OpenAI to make it suitable for a background/wallpaper.
     */
    protected function refinePromptWithOpenAi(string $userDescription): ?string
    {
        $systemPrompt = <<<'PROMPT'
You are an expert at crafting image generation prompts for beautiful desktop wallpapers.

Your task is to enhance a user's description into an optimal prompt for generating a stunning background image. The image should be crisp and detailed, not blurry.

Guidelines for the refined prompt:
1. FAITHFULLY preserve the user's original vision, theme, and subject matter
2. Keep the scene SHARP and DETAILED - do NOT add blur, soft focus, or haze
3. Compose the scene as a wide landscape suitable for a wallpaper (no centered focal points)
4. Spread visual interest across the frame rather than concentrating it in one spot
5. Use rich, vibrant colors appropriate to the scene
6. Add quality descriptors: "highly detailed", "8K resolution", "professional photography", "crisp details", "sharp focus"
7. Include lighting descriptors: "cinematic lighting", "golden hour", "dramatic lighting" as appropriate
8. For nature scenes: emphasize depth, layers, and atmosphere
9. For abstract scenes: emphasize patterns, textures, and color gradients
10. Avoid: people, faces, text, logos, or any distracting foreground elements

Keep the prompt concise (under 150 words) but descriptive.

IMPORTANT: Output ONLY the refined prompt. No explanations, no quotation marks, just the prompt text itself.
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Create a background wallpaper prompt based on this description:\n\n{$userDescription}"],
        ];

        $this->openAi->setTemperature(0.7);
        $this->openAi->setMaxTokens(500);

        $response = $this->openAi->chat($messages);

        if ($response === null) {
            Log::error('BackgroundImageService: OpenAI refinement failed', [
                'error' => $this->openAi->getError(),
            ]);

            return null;
        }

        $content = $response['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            Log::error('BackgroundImageService: No content in OpenAI response');

            return null;
        }

        return trim($content);
    }

    /**
     * Download an image from URL and save it to storage.
     */
    protected function saveImageToStorage(string $imageUrl): ?string
    {
        try {
            $contents = file_get_contents($imageUrl);

            if ($contents === false) {
                Log::error('BackgroundImageService: Failed to download image', [
                    'url' => $imageUrl,
                ]);

                return null;
            }

            $extension = $this->getExtensionFromUrl($imageUrl);
            $filename = 'theme-backgrounds/'.time().'_'.uniqid().'.'.$extension;

            if (Storage::disk('public')->put($filename, $contents)) {
                return Storage::disk('public')->url($filename);
            }

            Log::error('BackgroundImageService: Failed to save to storage');

            return null;
        } catch (\Exception $e) {
            Log::error('BackgroundImageService: Exception while saving image', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract file extension from URL.
     */
    protected function getExtensionFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Default to webp if we can't determine
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $extension : 'webp';
    }
}
