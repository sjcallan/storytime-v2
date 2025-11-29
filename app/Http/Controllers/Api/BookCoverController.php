<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\Replicate\ReplicateApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookCoverController extends Controller
{
    public function __construct(
        protected ReplicateApiService $replicateService
    ) {}

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
     * Generate book metadata using OpenAI.
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
            'cover_image_prompt' => 'A detailed visual description of a scene from the story',
        ];

        $systemPrompt = "You are a creative writing assistant helping to create metadata for a {$ageGroup} {$book->genre} {$book->type} story. ";
        $systemPrompt .= 'Based on the story details provided, generate a compelling title, a brief summary, and a detailed visual description of a key scene. ';
        $systemPrompt .= "\n\nIMPORTANT RULES FOR cover_image_prompt:\n";
        $systemPrompt .= "- Describe a visually striking SCENE from the story, NOT a book cover\n";
        $systemPrompt .= "- DO NOT mention 'book cover', 'title', 'text', 'letters', or any written words\n";
        $systemPrompt .= "- Focus on: characters, setting, mood, lighting, colors, action, and atmosphere\n";
        $systemPrompt .= "- Describe what you SEE in the scene, as if describing a painting or photograph\n";
        $systemPrompt .= "- Include specific visual details about character appearances and environment\n";
        $systemPrompt .= 'Respond ONLY with valid JSON in this exact format: '.json_encode($responseTemplate);

        $userMessage = "Story Details:\n";
        $userMessage .= "Genre: {$book->genre}\n";
        $userMessage .= "Type: {$book->type}\n";
        $userMessage .= "Age Level: {$book->age_level} years old\n";
        $userMessage .= "Plot: {$book->plot}\n";

        if ($book->scene) {
            $userMessage .= "Scene: {$book->scene}\n";
        }

        // Get characters
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

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];

        Log::info('BookCover: Making OpenAI request for metadata');

        $response = Http::withToken(config('services.openai.api_key'))
            ->timeout(60)
            ->post(config('services.openai.base_url', 'https://api.openai.com/v1').'/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.7,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            Log::error('BookCover: OpenAI request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        $completion = $data['choices'][0]['message']['content'] ?? '';

        Log::info('BookCover: Parsing OpenAI response');

        $parsed = json_decode($completion, true);

        if (! $parsed) {
            Log::error('BookCover: Failed to parse OpenAI response', ['completion' => $completion]);

            return null;
        }

        return [
            'title' => $parsed['title'] ?? 'Untitled Story',
            'summary' => $parsed['summary'] ?? '',
            'cover_image_prompt' => $parsed['cover_image_prompt'] ?? '',
        ];
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

        $result = $this->replicateService->generateImage($fullPrompt, null, '3:4');

        if ($result['error'] || empty($result['url'])) {
            Log::error('BookCover: Replicate image generation failed', [
                'error' => $result['error'],
            ]);

            return null;
        }

        // Download and save the image locally
        $imageUrl = $result['url'];
        $imagePath = $this->saveImageLocally($imageUrl, $book->id);

        if (! $imagePath) {
            Log::error('BookCover: Failed to save image locally');

            return $imageUrl;
        }

        Log::info('BookCover: Image saved', ['path' => $imagePath]);

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

    /**
     * Download image from URL and save to local storage.
     */
    protected function saveImageLocally(string $imageUrl, string $bookId): ?string
    {
        try {
            $response = Http::timeout(30)->get($imageUrl);

            if ($response->failed()) {
                return null;
            }

            $extension = 'webp';
            $filename = "covers/{$bookId}_".Str::random(8).".{$extension}";

            Storage::disk('public')->put($filename, $response->body());

            return "/storage/{$filename}";
        } catch (\Exception $e) {
            Log::error('BookCover: Error saving image', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
