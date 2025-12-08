<?php

namespace App\Services\Book;

use App\Models\Book;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiChatServiceInterface;
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

        try {
            // Set status to pending
            $this->bookService->updateById($bookId, ['cover_image_status' => 'pending']);

            // Generate cover image prompt using AI
            $coverImagePrompt = $this->getCoverImagePrompt($book);

            if (! $coverImagePrompt) {
                $this->bookService->updateById($bookId, ['cover_image_status' => 'error']);

                return false;
            }

            Log::info('BookCoverService: Cover image prompt generated', [
                'has_prompt' => ! empty($coverImagePrompt),
                'prompt_length' => strlen($coverImagePrompt),
            ]);

            // Update book with cover image prompt
            $this->bookService->updateById($bookId, [
                'cover_image_prompt' => $coverImagePrompt,
            ]);

            // Generate cover image using Replicate Flux 2
            $coverImagePath = $this->generateCoverImage($book, $coverImagePrompt);

            if ($coverImagePath) {
                $this->bookService->updateById($bookId, [
                    'cover_image' => $coverImagePath,
                    'cover_image_status' => 'complete',
                ]);
            } else {
                $this->bookService->updateById($bookId, ['cover_image_status' => 'error']);

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

            $this->bookService->updateById($bookId, ['cover_image_status' => 'error']);

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

        $systemPrompt = "You are a creative assistant helping to create a detailed visual description for generating a {$ageGroup} {$book->genre} {$book->type} story cover image. ";
        $systemPrompt .= 'Based on the story details provided, generate a highly detailed visual description that includes: scene composition, camera specifications, lighting setup, and technical camera settings. ';
        $systemPrompt .= "\n\nIMPORTANT RULES:\n";
        $systemPrompt .= "- Describe a visually striking SCENE from the story, NOT a book cover\n";
        $systemPrompt .= "- DO NOT mention 'book cover', 'title', 'text', 'letters', or any written words\n";
        $systemPrompt .= "- Focus on: characters, setting, mood, lighting, colors, action, and atmosphere\n";
        $systemPrompt .= "- Describe what you SEE in the scene, as if describing a painting or photograph\n";
        $systemPrompt .= "- Include specific visual details about character appearances and environment\n";
        $systemPrompt .= "- If the age level is teen or adult ensure the image is realistic, gritty and not cartoonish or manufactured.\n";
        $systemPrompt .= "- If the age level is pre-teen make sure the image is a gritty graphic novel style.\n";
        $systemPrompt .= "- If the age level is kids make sure the image is a bright, friendly cartoon style.\n";
        $systemPrompt .= "\nCHARACTER IDENTIFICATION RULES (CRITICAL):\n";
        $systemPrompt .= "- DO NOT use character names in the prompt\n";
        $systemPrompt .= "- Identify each character as '[Gender] [Number]' (e.g., 'Male 1', 'Female 2', 'Male 2')\n";
        $systemPrompt .= "- Number characters of the same gender sequentially (Male 1, Male 2, Female 1, etc.)\n";
        $systemPrompt .= "- Describe characters sequentially from LEFT to RIGHT across the image composition\n";
        $systemPrompt .= "- Include each character's physical description immediately after their identifier\n";
        $systemPrompt .= "- Example: 'On the left, Male 1, a tall elderly man with gray beard and weathered face, stands beside Female 1, a young girl with red braids and freckles, who is positioned center-frame...'\n";
        $systemPrompt .= "\nCAMERA AND TECHNICAL SPECIFICATIONS:\n";
        $systemPrompt .= "- Specify camera type and lens choice (e.g., 'Sony A7IV with 50mm f/1.4 lens', 'Canon EOS R5 with 85mm f/1.2')\n";
        $systemPrompt .= "- Include camera settings: aperture, ISO, shutter speed if relevant to the scene\n";
        $systemPrompt .= "- Describe lighting setup: natural light, golden hour, studio lighting, dramatic shadows, soft diffused light, etc.\n";
        $systemPrompt .= "- Specify dynamic range and color grading preferences\n";
        $systemPrompt .= "- Mention depth of field and focus points\n";
        $systemPrompt .= "\nRespond with ONLY the detailed visual prompt text. Do NOT use JSON format.";

        $userMessage = "Story Details:\n";
        $userMessage .= "Genre: {$book->genre}\n";
        $userMessage .= "Type: {$book->type}\n";
        $userMessage .= "Age Level: {$book->age_level} years old\n";
        $userMessage .= "Plot: {$book->plot}\n";
        $userMessage .= "Scene: {$book->scene}\n";

        if ($book->scene) {
            $userMessage .= "Scene: {$book->scene}\n";
        }

        $characters = $book->characters()->get();
        if ($characters->count() > 0) {
            $userMessage .= "Characters:\n";
            $genderCounts = ['male' => 0, 'female' => 0];

            foreach ($characters as $character) {
                $gender = strtolower($character->gender ?? 'unknown');
                if (isset($genderCounts[$gender])) {
                    $genderCounts[$gender]++;
                    $genderLabel = ucfirst($gender).' '.$genderCounts[$gender];
                } else {
                    $genderLabel = 'Character';
                }

                $userMessage .= "- {$genderLabel}";
                if ($character->age) {
                    $userMessage .= " ({$character->age} years old)";
                }
                if ($character->description) {
                    $userMessage .= ": {$character->description}";
                }
                $userMessage .= "\n";
            }
        }

        $userMessage .= "\nGenerate a highly detailed visual prompt for this scene, including camera specifications, lighting details, and technical camera settings.";

        Log::info('BookCoverService: Making AI request for cover image prompt');

        $this->chatService->resetMessages();
        $this->chatService->setModel('gpt-4.1');
        $this->chatService->setTemperature(0.5);
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

        // Add style modifiers based on genre and age
        $stylePrefix = $this->getStylePrefix($book);
        $fullPrompt = $stylePrefix.$prompt;

        Log::info('BookCoverService: Generating image with Replicate', [
            'prompt_length' => strlen($fullPrompt),
        ]);

        $result = $this->replicateService->generateImage($fullPrompt.' shot on Sony A7IV, clean sharp, high dynamic range', null, '3:4');

        if ($result['error'] || empty($result['url'])) {
            Log::error('BookCoverService: Replicate image generation failed', [
                'error' => $result['error'],
            ]);

            return null;
        }

        // Download and save the image to S3
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
