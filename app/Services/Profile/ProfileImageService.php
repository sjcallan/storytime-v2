<?php

namespace App\Services\Profile;

use App\Services\Ai\OpenAi\ApiService as OpenAiApiService;
use App\Services\Replicate\ReplicateApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileImageService
{
    public function __construct(
        protected OpenAiApiService $openAi,
        protected ReplicateApiService $replicate
    ) {}

    /**
     * Generate a profile avatar image from a user's description.
     *
     * @param  string|null  $userId  Optional user ID for tracking
     * @param  string|null  $profileId  Optional profile ID for tracking
     * @return array{url: string|null, error: string|null}
     */
    public function generateProfileImage(
        string $userDescription,
        ?string $userId = null,
        ?string $profileId = null
    ): array {
        Log::info('ProfileImageService: Starting profile image generation', [
            'user_description' => $userDescription,
        ]);

        $refinedPrompt = $this->refinePromptWithOpenAi($userDescription);

        if ($refinedPrompt === null) {
            return [
                'url' => null,
                'error' => 'Failed to refine the prompt. Please try again.',
            ];
        }

        Log::info('ProfileImageService: Prompt refined', [
            'original' => $userDescription,
            'refined' => $refinedPrompt,
        ]);

        $trackingContext = [
            'item_type' => 'profile_avatar',
            'user_id' => $userId,
            'profile_id' => $profileId,
        ];

        $result = $this->replicate->generateImage(
            prompt: $refinedPrompt,
            inputImages: null,
            aspectRatio: '1:1',
            trackingContext: $trackingContext
        );

        if ($result['error'] !== null || $result['url'] === null) {
            Log::error('ProfileImageService: Failed to generate image', [
                'error' => $result['error'],
            ]);

            return [
                'url' => null,
                'error' => $result['error'] ?? 'Failed to generate profile image.',
            ];
        }

        Log::info('ProfileImageService: Image generated', [
            'replicate_url' => $result['url'],
        ]);

        $savedPath = $this->saveImageToStorage($result['url']);

        if ($savedPath === null) {
            return [
                'url' => null,
                'error' => 'Failed to save the generated image.',
            ];
        }

        Log::info('ProfileImageService: Image saved', [
            'path' => $savedPath,
        ]);

        return [
            'url' => $savedPath,
            'error' => null,
        ];
    }

    /**
     * Refine the user's prompt using OpenAI to create a graphic novel style portrait.
     */
    protected function refinePromptWithOpenAi(string $userDescription): ?string
    {
        $systemPrompt = <<<'PROMPT'
You are an expert at crafting image generation prompts for stunning graphic novel style character portraits.

Your task is to enhance a user's description into an optimal prompt for generating a beautiful profile avatar in graphic novel/comic book art style.

Guidelines for the refined prompt:
1. FAITHFULLY preserve the user's original character description, personality, and key features
2. Style MUST be: graphic novel, comic book art, illustrated portrait, digital art
3. The image should be a portrait or head-and-shoulders composition, suitable for a profile avatar
4. Use bold lines, dynamic shading, and rich colors typical of graphic novels
5. Add quality descriptors: "highly detailed illustration", "professional comic art", "vibrant colors", "clean linework"
6. Include lighting that creates depth: "dramatic lighting", "cel shading", "volumetric lighting"
7. Background should be simple or abstract to keep focus on the character
8. The character should have expressive features and clear personality
9. Avoid: photorealism, blurry elements, cluttered backgrounds, text, logos
10. Make the character look heroic, memorable, and visually striking

Keep the prompt concise (under 150 words) but descriptive.

IMPORTANT: Output ONLY the refined prompt. No explanations, no quotation marks, just the prompt text itself.
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Create a graphic novel style profile avatar prompt based on this description:\n\n{$userDescription}"],
        ];

        $this->openAi->setTemperature(0.7);
        $this->openAi->setMaxTokens(500);

        $response = $this->openAi->chat($messages);

        if ($response === null) {
            Log::error('ProfileImageService: OpenAI refinement failed', [
                'error' => $this->openAi->getError(),
            ]);

            return null;
        }

        $content = $response['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            Log::error('ProfileImageService: No content in OpenAI response');

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
                Log::error('ProfileImageService: Failed to download image', [
                    'url' => $imageUrl,
                ]);

                return null;
            }

            $extension = $this->getExtensionFromUrl($imageUrl);
            $filename = 'profile-images/ai-'.time().'_'.uniqid().'.'.$extension;

            if (Storage::disk('public')->put($filename, $contents)) {
                return Storage::disk('public')->url($filename);
            }

            Log::error('ProfileImageService: Failed to save to storage');

            return null;
        } catch (\Exception $e) {
            Log::error('ProfileImageService: Exception while saving image', [
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

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $extension : 'webp';
    }
}
