<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiChatServiceInterface;
use App\Services\Replicate\ReplicateApiService;
use App\Traits\Service\SavesImagesToS3;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookCoverController extends Controller
{
    use SavesImagesToS3;

    protected AiChatServiceInterface $chatService;

    public function __construct(
        protected ReplicateApiService $replicateService,
        AiManager $aiManager
    ) {
        $this->chatService = $aiManager->chat();
    }

    /**
     * Generate book metadata (title, summary, cover image) using AI.
     */
    public function generate(Request $request, Book $book): JsonResponse
    {
        Log::info('BookCover: Generating metadata for book', ['book_id' => $book->id]);

        try {
            // Generate title, summary, and cover image prompt using OpenAI
            $metadata = $this->generateMetadata($book);

            if (! $metadata) {
                return response()->json(['error' => 'Failed to generate metadata'], 500);
            }

            Log::info('BookCover: Metadata generated', [
                'title' => $metadata['title'],
                'has_summary' => ! empty($metadata['summary']),
                'has_prompt' => ! empty($metadata['cover_image_prompt']),
            ]);

            // Update book with title, summary, and cover image prompt
            $book->update([
                'title' => $metadata['title'],
                'summary' => $metadata['summary'],
                'cover_image_prompt' => $metadata['cover_image_prompt'],
            ]);

            // Generate cover image using Replicate Flux 2
            $coverImagePath = $this->generateCoverImage($book, $metadata['cover_image_prompt']);

            if ($coverImagePath) {
                $book->update(['cover_image' => $coverImagePath]);
            }

            $book->refresh();

            return response()->json([
                'success' => true,
                'book' => $book,
            ]);
        } catch (\Exception $e) {
            Log::error('BookCover: Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate book metadata using AI service.
     *
     * @return array{title: string, summary: string, cover_image_prompt: string}|null
     */
    protected function generateMetadata(Book $book): ?array
    {
        $ageGroup = match (true) {
            $book->age_level <= 4 => 'toddler',
            $book->age_level <= 10 => 'children',
            $book->age_level <= 18 => 'teenage',
            default => 'adult',
        };

        $responseTemplate = [
            'title' => 'A compelling book title (6 words or less, no colons or punctuation)',
            'summary' => 'A 2-3 sentence summary of the story',
            'cover_image_prompt' => 'A detailed visual description of a scene from the story. describe the scene, the charcters, their appearance, clothing.',
        ];

        $systemPrompt = "You are a creative writing assistant helping to create metadata for a {$ageGroup} {$book->genre} {$book->type} story. ";
        $systemPrompt .= 'Based on the story details provided, generate a compelling title, a brief summary, and a detailed visual description of a key scene. ';
        $systemPrompt .= "\n\nIMPORTANT RULES FOR cover_image_prompt:\n";
        $systemPrompt .= "- Describe a visually striking SCENE from the story, NOT a book cover\n";
        $systemPrompt .= "- DO NOT mention 'book cover', 'title', 'text', 'letters', or any written words\n";
        $systemPrompt .= "- Focus on: characters, setting, mood, lighting, colors, action, and atmosphere\n";
        $systemPrompt .= "- Describe what you SEE in the scene, as if describing a painting or photograph\n";
        $systemPrompt .= "- Include specific visual details about character appearances and environment\n";
        $systemPrompt .= "- If the age level is teen or adult ensure the image is realistic, gritty and not cartoonish or manufactured.\n";
        $systemPrompt .= "- If the age level is pre-teen make sure the image is a gritty graphic novel style.\n";
        $systemPrompt .= "- If the age level is kids make sure the image is a bright, friendly cartoon style.\n";
        $systemPrompt .= 'Respond ONLY with valid JSON in this exact format: '.json_encode($responseTemplate);

        $userMessage = "Story Details:\n";
        $userMessage .= "Genre: {$book->genre}\n";
        $userMessage .= "Type: {$book->type}\n";
        $userMessage .= "Age Level: {$book->age_level} years old\n";
        $userMessage .= "Plot: {$book->plot}\n";

        if ($book->scene) {
            $userMessage .= "Scene: {$book->scene}\n";
        }

        $characters = $book->characters()->get();
        if ($characters->count() > 0) {
            $userMessage .= "Characters:\n";
            foreach ($characters as $character) {
                $userMessage .= "- {$character->name}";
                if ($character->age) {
                    $userMessage .= " ({$character->age} years old)";
                }
                if ($character->description) {
                    $userMessage .= ": {$character->description}";
                }
                $userMessage .= "\n";
            }
        }

        $userMessage .= "\nGenerate the title, summary, and cover image prompt for this story.";

        Log::info('BookCover: Making AI request for metadata');

        $this->chatService->resetMessages();
        $this->chatService->setResponseFormat('json_object');
        $this->chatService->addSystemMessage($systemPrompt);
        $this->chatService->addUserMessage($userMessage);

        $result = $this->chatService->chat();

        if (empty($result['completion'])) {
            Log::error('BookCover: AI request failed', [
                'error' => $result['error'] ?? 'Empty completion',
            ]);

            return null;
        }

        Log::info('BookCover: Parsing AI response');

        $parsed = $this->parseJsonResponse($result['completion']);

        if (! $parsed) {
            Log::error('BookCover: Failed to parse AI response', ['completion' => $result['completion']]);

            return null;
        }

        return [
            'title' => $parsed['title'] ?? 'Untitled Story',
            'summary' => $parsed['summary'] ?? '',
            'cover_image_prompt' => $parsed['cover_image_prompt'] ?? '',
        ];
    }

    /**
     * Parse JSON response, handling control characters that may be present in AI responses.
     *
     * @return array<string, mixed>|null
     */
    protected function parseJsonResponse(string $content): ?array
    {
        $result = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        $sanitized = $this->sanitizeJsonString($content);
        $result = json_decode($sanitized, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        Log::warning('BookCover: JSON parse failed after sanitization', [
            'error' => json_last_error_msg(),
        ]);

        return null;
    }

    /**
     * Sanitize a JSON string by escaping control characters inside string values.
     */
    protected function sanitizeJsonString(string $json): string
    {
        $result = '';
        $inString = false;
        $escape = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            $ord = ord($char);

            if ($escape) {
                $result .= $char;
                $escape = false;

                continue;
            }

            if ($char === '\\') {
                $result .= $char;
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;
                $result .= $char;

                continue;
            }

            if ($inString && $ord < 32) {
                switch ($ord) {
                    case 10:
                        $result .= '\\n';
                        break;
                    case 13:
                        $result .= '\\r';
                        break;
                    case 9:
                        $result .= '\\t';
                        break;
                    default:
                        $result .= sprintf('\\u%04x', $ord);
                        break;
                }

                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    /**
     * Generate cover image using Replicate Flux 2 and save locally.
     */
    protected function generateCoverImage(Book $book, string $prompt): ?string
    {
        if (empty($prompt)) {
            Log::warning('BookCover: Empty cover image prompt');

            return null;
        }

        // Add style modifiers based on genre and age
        $stylePrefix = $this->getStylePrefix($book);
        $fullPrompt = $stylePrefix.$prompt;

        Log::info('BookCover: Generating image with Replicate', [
            'prompt_length' => strlen($fullPrompt),
        ]);

        $result = $this->replicateService->generateImage($fullPrompt.' shot on Sony A7IV, clean sharp, high dynamic range', null, '3:4');

        if ($result['error'] || empty($result['url'])) {
            Log::error('BookCover: Replicate image generation failed', [
                'error' => $result['error'],
            ]);

            return null;
        }

        // Download and save the image to S3
        $imageUrl = $result['url'];
        $imagePath = $this->saveImageToS3($imageUrl, 'covers', $book->id);

        if (! $imagePath) {
            Log::error('BookCover: Failed to save image to S3');

            return $imageUrl;
        }

        Log::info('BookCover: Image saved to S3', ['path' => $imagePath]);

        return $imagePath;
    }

    /**
     * Get style prefix based on book genre and age level.
     */
    protected function getStylePrefix(Book $book): string
    {
        // CRITICAL: No text, letters, words, or writing on the image
        $style = 'NO TEXT, NO LETTERS, NO WORDS, NO WRITING, NO TITLES on the image. ';

        // Age-based style: cartoon for kids/pre-teens, realistic for teens/adults
        if ($book->age_level <= 13) {
            $style .= 'Graphic cartoon illustration in the style of Neil Gaiman and Dave McKean, ';
            $style .= 'whimsical yet slightly dark, hand-drawn aesthetic, rich textures, ';
            $style .= 'imaginative and dreamlike, vibrant colors with moody undertones, ';
        } else {
            $style .= 'Photorealistic digital art, cinematic lighting, ';
            $style .= 'highly detailed, professional quality, dramatic composition, ';
        }

        // Genre-specific mood additions
        $style .= match ($book->genre) {
            'fantasy' => 'magical atmosphere, ethereal lighting, mystical elements, ',
            'adventure' => 'dynamic action scene, exciting composition, sense of movement, ',
            'mystery' => 'mysterious atmosphere, dramatic shadows, intriguing mood, ',
            'science_fiction' => 'futuristic setting, sci-fi aesthetic, advanced technology, ',
            'fairy_tale' => 'enchanting and magical, storybook quality, wonder and imagination, ',
            'historical' => 'period-accurate details, historical setting, authentic atmosphere, ',
            'comedy' => 'fun and lighthearted mood, expressive characters, playful energy, ',
            'animal_stories' => 'expressive animal characters, heartwarming scene, natural setting, ',
            default => '',
        };

        return $style;
    }
}
