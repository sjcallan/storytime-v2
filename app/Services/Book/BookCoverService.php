<?php

namespace App\Services\Book;

use App\Events\Image\ImageGeneratedEvent;
use App\Models\Book;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiChatServiceInterface;
use App\Services\Image\ImageService;
use App\Services\Replicate\ReplicateApiService;
use App\Traits\Service\SavesImagesToS3;
use Illuminate\Support\Facades\Log;

class BookCoverService
{
    use SavesImagesToS3;

    protected AiChatServiceInterface $chatService;

    public function __construct(
        protected ReplicateApiService $replicateService,
        protected BookService $bookService,
        protected ImageService $imageService,
        AiManager $aiManager
    ) {
        $this->chatService = $aiManager->chat();
    }

    /**
     * Generate book cover image for a book.
     */
    public function generateCoverForBook(string $bookId): bool
    {
        Log::info('BookCoverService: Starting cover generation', ['book_id' => $bookId]);

        $book = $this->bookService->getById($bookId, ['*'], ['characters']);

        if (! $book) {
            Log::error('BookCoverService: Book not found', ['book_id' => $bookId]);

            return false;
        }

        // Get or create Image record
        $image = $this->imageService->getOrCreateBookCover($book);
        $this->imageService->markProcessing($image);

        try {
            // Generate cover image prompt using AI
            $coverImagePrompt = $this->getCoverImagePrompt($book);

            if (! $coverImagePrompt) {
                $this->imageService->markError($image, 'Failed to generate cover image prompt');
                event(new ImageGeneratedEvent($image->fresh()));

                return false;
            }

            Log::info('BookCoverService: Cover image prompt generated', [
                'has_prompt' => ! empty($coverImagePrompt),
                'prompt_length' => strlen($coverImagePrompt),
            ]);

            // Update Image record with prompt
            $this->imageService->updateById($image->id, ['prompt' => $coverImagePrompt]);

            // Generate cover image using Replicate Flux 2
            $coverImagePath = $this->generateCoverImage($book, $coverImagePrompt);

            if ($coverImagePath) {
                // Update book to point to Image record
                $this->bookService->updateById($bookId, [
                    'cover_image_id' => $image->id,
                ]);

                // Update Image record with the URL
                $this->imageService->markComplete($image, $coverImagePath);
                event(new ImageGeneratedEvent($image->fresh()));
            } else {
                $this->imageService->markError($image, 'Failed to generate cover image');
                event(new ImageGeneratedEvent($image->fresh()));

                return false;
            }

            Log::info('BookCoverService: Cover generation complete', ['book_id' => $bookId]);

            return true;
        } catch (\Exception $e) {
            Log::error('BookCoverService: Exception', [
                'book_id' => $bookId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->imageService->markError($image, $e->getMessage());
            event(new ImageGeneratedEvent($image->fresh()));

            return false;
        }
    }

    /**
     * Generate cover image prompt using AI service.
     */
    protected function getCoverImagePrompt(Book $book): ?string
    {
        $ageGroup = match (true) {
            $book->age_level <= 4 => 'toddler',
            $book->age_level <= 10 => 'children',
            $book->age_level <= 18 => 'teenage',
            default => 'adult',
        };

        $systemPrompt = "You are a creative assistant helping to create a concise visual description for generating a {$ageGroup} {$book->genre} {$book->type} story cover image. ";
        $systemPrompt .= "\n\nIMPORTANT RULES:\n";
        $systemPrompt .= "- Describe a visually striking SCENE from the story, NOT a book cover\n";
        $systemPrompt .= "- DO NOT mention 'book cover', 'title', 'text', 'letters', or any written words\n";
        $systemPrompt .= "- Focus on: characters, setting, mood, lighting, colors, and atmosphere\n";
        $systemPrompt .= "- Keep the description concise (2-3 paragraphs maximum)\n";
        $systemPrompt .= "- If the age level is teen or adult: realistic, cinematic style\n";
        $systemPrompt .= "- If the age level is pre-teen: graphic novel style\n";
        $systemPrompt .= "- If the age level is kids: bright, friendly cartoon style\n";
        $systemPrompt .= "\nCHARACTER RULES:\n";
        $systemPrompt .= "- DO NOT use character names\n";
        $systemPrompt .= "- Identify characters as '[Gender] [Number]' (e.g., 'Male 1', 'Female 1')\n";
        $systemPrompt .= "- Briefly describe each character's appearance\n";
        $systemPrompt .= "\nRespond with ONLY the visual prompt text. Be concise. Do NOT use JSON format.";

        $systemPrompt .= "Story Details:\n";
        $systemPrompt .= "Genre: {$book->genre}\n";
        $systemPrompt .= "Type: {$book->type}\n";
        $systemPrompt .= "Age Level: {$book->age_level} years old\n";
        $systemPrompt .= "Plot: {$book->plot}\n";
        $systemPrompt .= "Scene: {$book->scene}\n";

        if ($book->scene) {
            $systemPrompt .= "Scene: {$book->scene}\n";
        }

        $characters = $book->characters()->get();
        if ($characters->count() > 0) {
            $systemPrompt .= "Characters:\n";
            $genderCounts = ['male' => 0, 'female' => 0];

            foreach ($characters as $character) {
                $gender = strtolower($character->gender ?? 'unknown');
                if (isset($genderCounts[$gender])) {
                    $genderCounts[$gender]++;
                    $genderLabel = ucfirst($gender).' '.$genderCounts[$gender];
                } else {
                    $genderLabel = 'Character';
                }

                $systemPrompt .= "- {$genderLabel}";
                if ($character->age) {
                    $systemPrompt .= " ({$character->age} years old)";
                }
                if ($character->description) {
                    $systemPrompt .= ": {$character->description}";
                }
            }
        }

        $userMessage = 'Generate a concise visual prompt for this scene.';

        Log::info('BookCoverService: Making AI request for cover image prompt');

        $this->chatService->resetMessages();
        $this->chatService->setTemperature(0.5);
        $this->chatService->setResponseFormat('text');
        $this->chatService->addSystemMessage($systemPrompt);
        $this->chatService->addUserMessage($userMessage);

        $result = $this->chatService->chat();

        if (empty($result['completion'])) {
            Log::error('BookCoverService: AI request failed', [
                'error' => $result['error'] ?? 'Empty completion',
            ]);

            return null;
        }

        Log::info('BookCoverService: Cover image prompt generated');
        Log::info('BookCoverService: Cover image prompt', ['prompt' => $result['completion']]);

        return trim($result['completion']);
    }

    /**
     * Generate cover image using Replicate Flux 2 and save to S3.
     */
    protected function generateCoverImage(Book $book, string $prompt): ?string
    {
        if (empty($prompt)) {
            Log::warning('BookCoverService: Empty cover image prompt');

            return null;
        }

        $stylePrefix = $this->getStylePrefix($book);
        $fullPrompt = $stylePrefix.$prompt;

        // Collect all character portrait images for Flux 2 input
        $characterImages = $this->getCharacterPortraitUrls($book);

        Log::info('BookCoverService: Generating image with Replicate', [
            'prompt_length' => strlen($fullPrompt),
            'character_images_count' => count($characterImages),
        ]);

        $trackingContext = [
            'item_type' => 'book_cover',
            'user_id' => $book->user_id,
            'profile_id' => $book->profile_id,
            'book_id' => $book->id,
        ];

        $result = $this->replicateService->generateImage(
            $fullPrompt.' shot on Sony A7IV, clean sharp, high dynamic range',
            $characterImages,
            '3:4',
            $trackingContext
        );

        if ($result['error'] || empty($result['url'])) {
            Log::error('BookCoverService: Replicate image generation failed', [
                'error' => $result['error'],
            ]);

            return null;
        }

        $imageUrl = $result['url'];
        $imagePath = $this->saveImageToS3($imageUrl, 'covers', $book->id);

        if (! $imagePath) {
            Log::error('BookCoverService: Failed to save image to S3');

            return $imageUrl;
        }

        Log::info('BookCoverService: Image saved to S3', ['path' => $imagePath]);

        return $imagePath;
    }

    /**
     * Get all character portrait URLs for the book.
     *
     * @return array<string>
     */
    protected function getCharacterPortraitUrls(Book $book): array
    {
        $characterImages = [];

        $characters = $book->characters ?? $book->characters()->get();

        foreach ($characters as $character) {
            $portraitUrl = $character->portrait_image_url;
            if ($portraitUrl) {
                $characterImages[] = $portraitUrl;
                Log::debug('BookCoverService: Added character portrait for cover', [
                    'character_id' => $character->id,
                    'character_name' => $character->name,
                ]);
            }
        }

        return $characterImages;
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
