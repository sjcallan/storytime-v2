<?php

namespace App\Services\Image;

use App\Enums\ImageType;
use App\Events\Image\ImageGeneratedEvent;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\Image;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiChatServiceInterface;
use App\Services\Book\BookService;
use App\Services\Replicate\ReplicateApiService;
use App\Traits\Service\SavesImagesToS3;
use Illuminate\Support\Facades\Log;

class ImageGenerationService
{
    use SavesImagesToS3;

    protected AiChatServiceInterface $chatService;

    public function __construct(
        protected ImageService $imageService,
        protected ReplicateApiService $replicateService,
        protected BookService $bookService,
        AiManager $aiManager
    ) {
        $this->chatService = $aiManager->chat();
    }

    /**
     * Generate an image based on its type.
     *
     * @param  array<string>  $inputImageUrls  Optional input images for style consistency
     */
    public function generate(Image $image, array $inputImageUrls = []): Image
    {
        Log::info('[ImageGenerationService::generate] Starting generation', [
            'image_id' => $image->id,
            'type' => $image->type->value,
            'input_image_count' => count($inputImageUrls),
        ]);

        $this->imageService->markProcessing($image);

        try {
            $result = match ($image->type) {
                ImageType::BookCover => $this->generateBookCover($image),
                ImageType::CharacterPortrait => $this->generateCharacterPortrait($image),
                ImageType::ChapterHeader => $this->generateChapterHeader($image),
                ImageType::ChapterInline => $this->generateChapterInline($image, $inputImageUrls),
                ImageType::Manual => $this->generateManualImage($image, $inputImageUrls),
            };

            if ($result) {
                $image = $this->imageService->markComplete($image, $result);
                Log::info('[ImageGenerationService::generate] Generation complete', [
                    'image_id' => $image->id,
                    'image_url' => $result,
                ]);
            } else {
                $image = $this->imageService->markError($image, 'Failed to generate image');
                Log::error('[ImageGenerationService::generate] Generation failed - no result', [
                    'image_id' => $image->id,
                ]);
            }

            event(new ImageGeneratedEvent($image));

            return $image;
        } catch (\Throwable $e) {
            Log::error('[ImageGenerationService::generate] Exception', [
                'image_id' => $image->id,
                'error' => $e->getMessage(),
            ]);

            $image = $this->imageService->markError($image, $e->getMessage());
            event(new ImageGeneratedEvent($image));

            return $image;
        }
    }

    /**
     * Generate a book cover image.
     */
    protected function generateBookCover(Image $image): ?string
    {
        $book = $image->book;

        if (! $book) {
            return null;
        }

        $book->load(['characters.portraitImage', 'coverImage']);

        // Generate prompt if not already set
        $prompt = $image->prompt;
        if (! $prompt) {
            $prompt = $this->generateBookCoverPrompt($book);
            $this->imageService->updateById($image->id, ['prompt' => $prompt]);
        }

        $stylePrefix = $this->getBookCoverStylePrefix($book);
        $fullPrompt = $stylePrefix.$prompt.' shot on Sony A7IV, clean sharp, high dynamic range';

        // Save the full prompt that will be sent to Replicate
        $this->imageService->updateById($image->id, ['prompt' => $fullPrompt]);

        // Get character portrait URLs for input images
        $characterImages = $this->getCharacterPortraitUrls($book);

        $trackingContext = [
            'item_type' => 'book_cover',
            'user_id' => $image->user_id,
            'profile_id' => $image->profile_id,
            'book_id' => $image->book_id,
        ];

        $result = $this->replicateService->generateImage(
            $fullPrompt,
            $characterImages,
            $image->aspect_ratio,
            $trackingContext
        );

        if ($result['error'] || empty($result['url'])) {
            Log::error('[ImageGenerationService::generateBookCover] API error', [
                'error' => $result['error'],
            ]);

            return null;
        }

        return $this->saveImageToS3($result['url'], 'covers', $book->id);
    }

    /**
     * Generate a character portrait image.
     */
    protected function generateCharacterPortrait(Image $image): ?string
    {
        $character = $image->character;

        if (! $character) {
            return null;
        }

        $character->load('book');

        // Generate prompt if not already set
        $prompt = $image->prompt;
        if (! $prompt) {
            $prompt = $this->generateCharacterPortraitPrompt($character);
            $this->imageService->updateById($image->id, ['prompt' => $prompt]);
        }

        $ageLevel = $character->book?->age_level ?? 18;
        $useKreaModel = $ageLevel > 15;

        $trackingContext = [
            'item_type' => 'character_portrait',
            'user_id' => $image->user_id,
            'profile_id' => $image->profile_id,
            'book_id' => $image->book_id,
            'character_id' => $image->character_id,
        ];

        $result = $useKreaModel
            ? $this->replicateService->generateImageWithKrea(
                prompt: $prompt,
                aspectRatio: $image->aspect_ratio,
                trackingContext: $trackingContext
            )
            : $this->replicateService->generateImage(
                prompt: $prompt,
                inputImages: null,
                aspectRatio: $image->aspect_ratio,
                trackingContext: $trackingContext
            );

        if ($result['error'] || empty($result['url'])) {
            Log::error('[ImageGenerationService::generateCharacterPortrait] API error', [
                'error' => $result['error'],
            ]);

            return null;
        }

        return $this->saveImageToS3($result['url'], 'portraits', $character->id);
    }

    /**
     * Generate a chapter header image.
     */
    protected function generateChapterHeader(Image $image): ?string
    {
        $chapter = $image->chapter;

        if (! $chapter) {
            return null;
        }

        $chapter->load(['book.characters.portraitImage', 'book.coverImage']);
        $book = $chapter->book;

        $prompt = $image->prompt;

        if (! $prompt) {
            return null;
        }

        $style = $this->getSceneImageStylePrefix($book);
        $fullPrompt = trim($style.' '.$this->stripQuotes($prompt));

        // Save the full prompt that will be sent to Replicate
        $this->imageService->updateById($image->id, ['prompt' => $fullPrompt]);

        // Get reference images
        $inputImages = [];

        // Use the cover_image_url accessor which prioritizes Image model over legacy field
        $coverUrl = $book->cover_image_url;
        if ($coverUrl) {
            $inputImages[] = $coverUrl;
        }

        // Add character portraits using portrait_image_url accessor
        foreach ($book->characters ?? [] as $character) {
            $portraitUrl = $character->portrait_image_url;
            if ($portraitUrl) {
                $inputImages[] = $portraitUrl;
            }
        }

        $trackingContext = [
            'item_type' => 'chapter_header',
            'user_id' => $image->user_id,
            'profile_id' => $image->profile_id,
            'book_id' => $image->book_id,
            'chapter_id' => $image->chapter_id,
        ];

        $result = $this->replicateService->generateImage(
            $fullPrompt,
            $inputImages,
            $image->aspect_ratio,
            $trackingContext
        );

        if ($result['error'] || empty($result['url'])) {
            Log::error('[ImageGenerationService::generateChapterHeader] API error', [
                'error' => $result['error'],
            ]);

            return null;
        }

        return $this->saveImageToS3($result['url'], 'chapters', $chapter->id);
    }

    /**
     * Generate a chapter inline image.
     *
     * @param  array<string>  $providedInputImages  Optional explicitly provided input images
     */
    protected function generateChapterInline(Image $image, array $providedInputImages = []): ?string
    {
        $book = $image->book;

        if (! $book) {
            return null;
        }

        $prompt = $image->prompt;

        if (! $prompt) {
            return null;
        }

        // Check if the prompt is already JSON (custom image with Flux 2 schema)
        $isJsonPrompt = $this->isJsonString($prompt);

        // Build the full prompt
        if ($isJsonPrompt) {
            // Already in Flux 2 JSON format, use as-is
            $fullPrompt = $prompt;
        } elseif ($this->isFlux2Model()) {
            // Convert regular prompt to Flux 2 JSON format
            $book->load(['characters.portraitImage', 'coverImage']);
            $fullPrompt = $this->buildFlux2JsonPrompt($book, $prompt);
        } else {
            $style = $this->getSceneImageStylePrefix($book);
            $fullPrompt = trim($style.' '.$this->stripQuotes($prompt));
        }

        // Determine input images - use provided ones if available, otherwise collect from book
        if (! empty($providedInputImages)) {
            // Use explicitly provided input images (for custom images)
            $inputImages = $providedInputImages;

            // Also add the cover image for style consistency
            $coverUrl = $book->cover_image_url;
            if ($coverUrl && ! in_array($coverUrl, $inputImages)) {
                array_unshift($inputImages, $coverUrl);
            }
        } else {
            // Load chapter if this is a chapter-associated image
            $chapter = $image->chapter;
            if ($chapter) {
                $chapter->load(['book.characters.portraitImage', 'book.coverImage']);
                $book = $chapter->book;
            } else {
                $book->load(['characters.portraitImage', 'coverImage']);
            }

            // Collect base images for style consistency
            $baseImages = [];

            // Use the cover_image_url accessor which prioritizes Image model over legacy field
            $coverUrl = $book->cover_image_url;
            if ($coverUrl) {
                $baseImages[] = $coverUrl;
            }

            // Build character portrait map using portrait_image_url accessor
            $characterPortraits = [];
            foreach ($book->characters ?? [] as $character) {
                $portraitUrl = $character->portrait_image_url;
                if ($portraitUrl) {
                    $characterPortraits[$character->name] = $portraitUrl;
                }
            }

            // Get character images for this specific scene
            $sceneCharacterImages = $this->getCharacterImagesForScene(
                $prompt,
                $book->characters,
                $characterPortraits,
                [
                    'book_id' => $book->id,
                    'chapter_id' => $chapter?->id,
                    'user_id' => $book->user_id,
                    'profile_id' => $book->profile_id,
                ]
            );

            $inputImages = array_merge($baseImages, $sceneCharacterImages);
        }

        // Save the full prompt that will be sent to Replicate (only if it was transformed)
        if ($fullPrompt !== $prompt) {
            $this->imageService->updateById($image->id, ['prompt' => $fullPrompt]);
        }

        $trackingContext = [
            'item_type' => 'chapter_inline_image',
            'user_id' => $image->user_id,
            'profile_id' => $image->profile_id,
            'book_id' => $image->book_id,
            'chapter_id' => $image->chapter_id,
        ];

        $result = $this->replicateService->generateImage(
            $fullPrompt,
            $inputImages,
            $image->aspect_ratio,
            $trackingContext
        );

        if ($result['error'] || empty($result['url'])) {
            Log::error('[ImageGenerationService::generateChapterInline] API error', [
                'error' => $result['error'],
            ]);

            return null;
        }

        $chapter = $image->chapter;
        $filename = $chapter
            ? $chapter->id.'_scene_'.$image->paragraph_index
            : 'book_'.$image->book_id.'_'.$image->id;

        return $this->saveImageToS3(
            $result['url'],
            'chapters/inline',
            $filename
        );
    }

    /**
     * Generate a manual/custom image created by the user.
     *
     * @param  array<string>  $providedInputImages  Optional explicitly provided input images
     */
    protected function generateManualImage(Image $image, array $providedInputImages = []): ?string
    {
        $book = $image->book;

        if (! $book) {
            return null;
        }

        $prompt = $image->prompt;

        if (! $prompt) {
            return null;
        }

        // For manual images, the prompt is already in Flux 2 JSON format
        $fullPrompt = $prompt;

        // Determine input images - use provided ones if available
        $inputImages = $providedInputImages;

        // Also add the cover image for style consistency
        $coverUrl = $book->cover_image_url;
        if ($coverUrl && ! in_array($coverUrl, $inputImages)) {
            array_unshift($inputImages, $coverUrl);
        }

        $trackingContext = [
            'item_type' => 'manual_image',
            'user_id' => $image->user_id,
            'profile_id' => $image->profile_id,
            'book_id' => $image->book_id,
        ];

        $result = $this->replicateService->generateImage(
            $fullPrompt,
            $inputImages,
            $image->aspect_ratio,
            $trackingContext
        );

        if ($result['error'] || empty($result['url'])) {
            Log::error('[ImageGenerationService::generateManualImage] API error', [
                'error' => $result['error'],
            ]);

            return null;
        }

        return $this->saveImageToS3(
            $result['url'],
            'manual',
            'book_'.$image->book_id.'_'.$image->id
        );
    }

    /**
     * Generate book cover prompt using AI.
     */
    protected function generateBookCoverPrompt(Book $book): string
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

        $systemPrompt .= "\n\nStory Details:\n";
        $systemPrompt .= "Genre: {$book->genre}\n";
        $systemPrompt .= "Type: {$book->type}\n";
        $systemPrompt .= "Age Level: {$book->age_level} years old\n";
        $systemPrompt .= "Plot: {$book->plot}\n";

        if ($book->scene) {
            $systemPrompt .= "Scene: {$book->scene}\n";
        }

        $characters = $book->characters()->get();
        if ($characters->count() > 0) {
            $systemPrompt .= "\nCharacters:\n";
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
                $systemPrompt .= "\n";
            }
        }

        $this->chatService->resetMessages();
        $this->chatService->setTemperature(0.5);
        $this->chatService->setResponseFormat('text');
        $this->chatService->addSystemMessage($systemPrompt);
        $this->chatService->addUserMessage('Generate a concise visual prompt for this scene.');

        $result = $this->chatService->chat();

        return trim($result['completion'] ?? '');
    }

    /**
     * Generate character portrait prompt using AI.
     */
    protected function generateCharacterPortraitPrompt(Character $character): string
    {
        $ageLevel = $character->book?->age_level ?? 18;
        $genre = $character->book?->genre ?? 'general fiction';

        $ageStyle = $this->getAgeAppropriateStyleDescription($ageLevel);
        $genreStyle = $this->getGenreStyleDescription($genre);

        $styleGuidance = "Art Style: {$ageStyle}";
        if ($genreStyle) {
            $styleGuidance .= "\nGenre Style: {$genreStyle}";
        }

        $systemPrompt = "You are an expert at writing prompts for AI image generation models. Your task is to write a single, detailed description of a character portrait headshot.
{$styleGuidance}

Write a clear, detailed description of the portrait headshot. Focus on:
- Physical appearance and facial features
- Expression and emotion
- Lighting and composition appropriate to the style
- Technical photography details (if realistic style), including camera type and lens choice, aperture, ISO, shutter speed, and focal length.
- Age-appropriate styling

Be specific and descriptive. Write only the description itself, no explanations or meta-commentary.";

        $userPrompt = "Create an image generation prompt for a portrait headshot of this character:\n\n";
        if ($character->name) {
            $userPrompt .= "Name: {$character->name}\n";
        }
        if ($character->age) {
            $userPrompt .= "Age: {$character->age} years old\n";
        }
        if ($character->gender) {
            $userPrompt .= "Gender: {$character->gender}\n";
        }
        if ($character->nationality) {
            $userPrompt .= "Heritage: {$character->nationality}\n";
        }
        if ($character->description) {
            $userPrompt .= "Description: {$character->description}\n";
        }
        $userPrompt .= "\nBook Genre: {$genre}\n";
        $userPrompt .= "Target Age Level: {$ageLevel}";

        $this->chatService->resetMessages();
        $this->chatService->setMaxTokens(500);
        $this->chatService->setTemperature(0.5);
        $this->chatService->addSystemMessage($systemPrompt);
        $this->chatService->addUserMessage($userPrompt);

        $response = $this->chatService->chat();

        if (isset($response['error']) || empty($response['completion'])) {
            return $this->buildFallbackPortraitPrompt($character);
        }

        return trim($response['completion']);
    }

    /**
     * Get the style prefix for book covers.
     */
    protected function getBookCoverStylePrefix(Book $book): string
    {
        $style = 'NO TEXT, NO LETTERS, NO WORDS, NO WRITING, NO TITLES on the image. ';

        if ($book->age_level <= 13) {
            $style .= 'Graphic cartoon illustration in the style of Neil Gaiman and Dave McKean, ';
            $style .= 'whimsical yet slightly dark, hand-drawn aesthetic, rich textures, ';
            $style .= 'imaginative and dreamlike, vibrant colors with moody undertones, ';
        } else {
            $style .= 'Photorealistic digital art, cinematic lighting, ';
            $style .= 'highly detailed, professional quality, dramatic composition, ';
        }

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
     * Get style prefix for scene images.
     */
    protected function getSceneImageStylePrefix(Book $book): string
    {
        $style = '';

        if ($book->age_level <= 13) {
            $style .= 'Cartoon illustration in the style of Neil Gaiman and Dave McKean, ';
            $style .= 'whimsical yet slightly dark, hand-drawn aesthetic, rich textures, ';
            $style .= 'imaginative and dreamlike, vibrant colors with moody undertones, ';
        } else {
            $style .= 'Photorealistic, professional quality, dramatic composition, ';
        }

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
     * Get age-appropriate art style description.
     */
    protected function getAgeAppropriateStyleDescription(int $ageLevel): string
    {
        if ($ageLevel <= 4) {
            return 'Bright, friendly cartoon style with soft rounded features, suitable for toddlers';
        }

        if ($ageLevel <= 9) {
            return 'Colorful cartoon style with expressive features, suitable for young children';
        }

        if ($ageLevel <= 12) {
            return 'Stylized cartoon/illustration style, suitable for pre-teens';
        }

        if ($ageLevel <= 15) {
            return 'Semi-realistic illustration style with some stylization, suitable for teens';
        }

        return 'Realistic photographic style with natural lighting and film-like quality, photographed with professional camera equipment like Nikon Z8 with 85mm f/1.8 lens for authentic depth of field';
    }

    /**
     * Get genre-specific style description.
     */
    protected function getGenreStyleDescription(string $genre): string
    {
        return match (strtolower($genre)) {
            'children' => 'Warm, inviting colors that are comforting and friendly',
            'science fiction' => 'Futuristic aesthetic inspired by classic sci-fi like Star Trek, with clean lines and technological elements',
            'romance' => 'Fine art portrait style with soft, flattering lighting and romantic atmosphere',
            'horror' => 'Gothic atmosphere with dramatic shadows and slightly unsettling mood',
            'fantasy' => 'Mystical and imaginative, inspired by authors like Neil Gaiman',
            'adventure' => 'Dynamic composition with adventurous, energetic mood',
            'mystery' => 'Film noir inspired with moody lighting and intriguing atmosphere',
            default => '',
        };
    }

    /**
     * Build a fallback portrait prompt.
     */
    protected function buildFallbackPortraitPrompt(Character $character): string
    {
        $ageLevel = $character->book?->age_level ?? 18;
        $style = $this->getAgeAppropriateStyleDescription($ageLevel);

        $parts = ['Portrait headshot', $style, 'of a character'];

        if ($character->age) {
            $parts[] = "{$character->age} years old";
        }
        if ($character->gender) {
            $parts[] = $character->gender;
        }
        if ($character->nationality) {
            $parts[] = "{$character->nationality} heritage";
        }
        if ($character->description) {
            $parts[] = $character->description;
        }

        return implode(', ', $parts);
    }

    /**
     * Get all character portrait URLs for a book.
     *
     * @return array<string>
     */
    protected function getCharacterPortraitUrls(Book $book): array
    {
        $characterImages = [];
        $characters = $book->characters ?? $book->characters()->get();

        foreach ($characters as $character) {
            // Use the portrait_image_url accessor which prioritizes Image model over legacy field
            $portraitUrl = $character->portrait_image_url;
            if ($portraitUrl) {
                $characterImages[] = $portraitUrl;
            }
        }

        return $characterImages;
    }

    /**
     * Check if the current image generation model is FLUX 2.
     */
    protected function isFlux2Model(): bool
    {
        return ! (bool) config('services.replicate.use_custom_model', false);
    }

    /**
     * Build a JSON-structured prompt for FLUX 2 model.
     */
    protected function buildFlux2JsonPrompt(Book $book, string $sceneDescription): string
    {
        $characters = $book->characters ?? collect();
        $subjects = $this->extractSubjectsFromScene($sceneDescription, $characters);
        $styleConfig = $this->getFlux2StyleConfig($book);

        $promptData = [
            'scene' => $this->stripQuotes($sceneDescription),
            'subjects' => $subjects,
            'style' => $styleConfig['style'],
            'color_palette' => $styleConfig['color_palette'],
            'lighting' => $styleConfig['lighting'],
            'mood' => $styleConfig['mood'],
            'background' => $this->extractBackgroundFromScene($sceneDescription),
            'composition' => '16:9 landscape, cinematic framing, balanced composition',
            'camera' => [
                'angle' => $styleConfig['camera_angle'],
                'lens' => $styleConfig['lens'],
                'depth_of_field' => $styleConfig['depth_of_field'],
            ],
        ];

        return json_encode($promptData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get consistent style configuration for FLUX 2 based on book attributes.
     *
     * @return array{style: string, color_palette: array<string>, lighting: string, mood: string, camera_angle: string, lens: string, depth_of_field: string}
     */
    protected function getFlux2StyleConfig(Book $book): array
    {
        $isChildFriendly = $book->age_level <= 13;

        if ($isChildFriendly) {
            $baseStyle = 'Whimsical cartoon illustration in the style of Neil Gaiman and Dave McKean, hand-drawn aesthetic, rich textures, imaginative and dreamlike';
            $defaultPalette = ['#FFB347', '#87CEEB', '#98D8AA', '#F5E6CC', '#E8B4BC'];
            $defaultLighting = 'Warm, soft lighting with gentle shadows, storybook quality';
            $defaultCameraAngle = 'eye-level or slightly low angle for wonder';
            $defaultLens = 'standard lens, natural perspective';
            $defaultDepthOfField = 'moderate depth, all elements visible';
        } else {
            $baseStyle = 'Photorealistic, professional quality, dramatic composition, cinematic';
            $defaultPalette = ['#2C3E50', '#34495E', '#7F8C8D', '#ECF0F1', '#BDC3C7'];
            $defaultLighting = 'Dramatic natural lighting with atmospheric depth';
            $defaultCameraAngle = 'dynamic cinematic angle';
            $defaultLens = '35mm cinematic lens';
            $defaultDepthOfField = 'shallow depth of field, bokeh background';
        }

        $genreConfig = match ($book->genre) {
            'fantasy' => [
                'mood' => 'magical, ethereal, mystical wonder',
                'palette' => $isChildFriendly
                    ? ['#9B59B6', '#3498DB', '#F39C12', '#1ABC9C', '#E8DAEF']
                    : ['#4A235A', '#1A5276', '#7D3C98', '#2E4053', '#D4AC0D'],
                'lighting' => 'Ethereal magical glow with shimmering highlights',
            ],
            'adventure' => [
                'mood' => 'exciting, dynamic, sense of movement and discovery',
                'palette' => $isChildFriendly
                    ? ['#E74C3C', '#F39C12', '#27AE60', '#3498DB', '#F5B041']
                    : ['#922B21', '#B9770E', '#1E8449', '#21618C', '#D68910'],
                'lighting' => 'Bright, adventurous lighting with strong contrasts',
            ],
            'mystery' => [
                'mood' => 'mysterious, intriguing, atmospheric suspense',
                'palette' => $isChildFriendly
                    ? ['#5D6D7E', '#85929E', '#ABB2B9', '#F4D03F', '#2E4053']
                    : ['#1C2833', '#273746', '#566573', '#839192', '#F7DC6F'],
                'lighting' => 'Dramatic shadows with pools of light, film noir inspired',
            ],
            'science_fiction' => [
                'mood' => 'futuristic, technological wonder, otherworldly',
                'palette' => $isChildFriendly
                    ? ['#00BCD4', '#7C4DFF', '#00E676', '#FF4081', '#B388FF']
                    : ['#0097A7', '#512DA8', '#00796B', '#C2185B', '#7B1FA2'],
                'lighting' => 'Cool technological lighting with neon accents',
            ],
            'fairy_tale' => [
                'mood' => 'enchanting, magical, wonder and imagination',
                'palette' => ['#FADBD8', '#D5F5E3', '#FCF3CF', '#D4E6F1', '#F5EEF8'],
                'lighting' => 'Soft, dreamy lighting with sparkles and glows',
            ],
            default => [
                'mood' => 'engaging, story-driven, emotionally resonant',
                'palette' => $defaultPalette,
                'lighting' => $defaultLighting,
            ],
        };

        return [
            'style' => $baseStyle,
            'color_palette' => $genreConfig['palette'] ?? $defaultPalette,
            'lighting' => $genreConfig['lighting'] ?? $defaultLighting,
            'mood' => $genreConfig['mood'] ?? 'engaging and story-driven',
            'camera_angle' => $defaultCameraAngle,
            'lens' => $defaultLens,
            'depth_of_field' => $defaultDepthOfField,
        ];
    }

    /**
     * Extract subject descriptions from scene.
     *
     * @param  \Illuminate\Support\Collection|null  $characters
     * @return array<array{description: string, position: string, action: string}>
     */
    protected function extractSubjectsFromScene(string $sceneDescription, $characters): array
    {
        $subjects = [];
        $scene = strtolower($sceneDescription);

        $characterMap = [];
        $maleCount = 0;
        $femaleCount = 0;

        if ($characters && $characters->count() > 0) {
            foreach ($characters as $character) {
                $gender = strtolower($character->gender ?? 'unknown');

                if ($gender === 'male') {
                    $maleCount++;
                    $identifier = 'male '.$maleCount;
                } elseif ($gender === 'female') {
                    $femaleCount++;
                    $identifier = 'female '.$femaleCount;
                } else {
                    continue;
                }

                $description = [];
                if ($character->age) {
                    $description[] = "{$character->age} years old";
                }
                if ($character->gender) {
                    $description[] = $character->gender;
                }
                if ($character->nationality) {
                    $description[] = $character->nationality;
                }
                if ($character->description) {
                    $description[] = $character->description;
                }

                $characterMap[$identifier] = [
                    'name' => $character->name,
                    'full_description' => implode(', ', $description),
                ];
            }
        }

        foreach ($characterMap as $identifier => $charData) {
            if (str_contains($scene, $identifier)) {
                $subjects[] = [
                    'description' => $charData['full_description'] ?: "A {$identifier} character",
                    'position' => $this->estimatePositionFromScene($sceneDescription, $identifier, count($subjects)),
                    'action' => $this->extractActionForCharacter($sceneDescription, $identifier),
                ];
            }
        }

        if (empty($subjects)) {
            $subjects[] = [
                'description' => 'Main subject of the scene',
                'position' => 'center of frame',
                'action' => 'as described in scene',
            ];
        }

        return $subjects;
    }

    /**
     * Extract background details from scene description.
     */
    protected function extractBackgroundFromScene(string $sceneDescription): string
    {
        $backgroundKeywords = [
            'forest', 'woods', 'trees', 'meadow', 'field', 'garden',
            'castle', 'palace', 'house', 'room', 'kitchen', 'bedroom', 'library',
            'city', 'street', 'town', 'village', 'market',
            'mountain', 'hill', 'valley', 'river', 'lake', 'ocean', 'beach',
            'sky', 'clouds', 'stars', 'moon', 'sun', 'sunset', 'sunrise',
            'cave', 'dungeon', 'tower', 'bridge', 'path', 'road',
        ];

        $foundBackgrounds = [];
        $sceneLower = strtolower($sceneDescription);

        foreach ($backgroundKeywords as $keyword) {
            if (str_contains($sceneLower, $keyword)) {
                $foundBackgrounds[] = $keyword;
            }
        }

        if (! empty($foundBackgrounds)) {
            return 'Setting features: '.implode(', ', array_slice($foundBackgrounds, 0, 4));
        }

        return 'Contextual background matching the scene description';
    }

    /**
     * Extract action description for a character.
     */
    protected function extractActionForCharacter(string $sceneDescription, string $characterIdentifier): string
    {
        $actionVerbs = [
            'running', 'walking', 'standing', 'sitting', 'lying', 'jumping',
            'looking', 'watching', 'staring', 'gazing', 'searching',
            'holding', 'carrying', 'reaching', 'touching', 'grabbing',
            'talking', 'speaking', 'whispering', 'shouting', 'laughing', 'crying',
            'fighting', 'hiding', 'chasing', 'escaping', 'climbing',
            'reading', 'writing', 'cooking', 'eating', 'drinking',
            'flying', 'swimming', 'dancing', 'singing', 'playing',
        ];

        $sceneLower = strtolower($sceneDescription);

        foreach ($actionVerbs as $verb) {
            if (str_contains($sceneLower, $verb)) {
                return ucfirst($verb);
            }
        }

        return 'engaged in the scene';
    }

    /**
     * Estimate character position based on scene.
     */
    protected function estimatePositionFromScene(string $sceneDescription, string $characterIdentifier, int $subjectIndex): string
    {
        $positions = [
            'left side of frame',
            'center of frame',
            'right side of frame',
            'foreground',
            'background',
        ];

        return $positions[$subjectIndex % count($positions)];
    }

    /**
     * Identify characters present in a scene using AI.
     *
     * @param  \Illuminate\Support\Collection  $characters
     * @param  array<string, string>  $characterPortraits
     * @param  array{book_id: string, chapter_id: string, user_id: string, profile_id: string|null}|null  $trackingContext
     * @return array<string>
     */
    protected function getCharacterImagesForScene(string $scenePrompt, $characters, array $characterPortraits, ?array $trackingContext = null): array
    {
        if ($characters->isEmpty() || empty($characterPortraits)) {
            return [];
        }

        $characterNames = $characters->pluck('name')->filter()->toArray();

        if (empty($characterNames)) {
            return [];
        }

        try {
            $this->chatService->resetMessages();
            $this->chatService->setMaxTokens(200);
            $this->chatService->setTemperature(0);
            $this->chatService->setResponseFormat('json_object');

            $systemPrompt = 'You identify which characters from a story are present in a scene description. ';
            $systemPrompt .= 'Analyze the scene prompt and determine which of the provided characters appear or are mentioned. ';
            $systemPrompt .= 'Consider gender identifiers like "Male 1", "Female 1" etc. and match them to the characters based on their order. ';
            $systemPrompt .= 'Return a JSON object with a single key "characters" containing an array of character names that are present.';

            $userPrompt = "Characters in this story (in order):\n";
            $maleCount = 0;
            $femaleCount = 0;
            foreach ($characters as $character) {
                $gender = strtolower($character->gender ?? 'unknown');
                if ($gender === 'male') {
                    $maleCount++;
                    $identifier = "Male {$maleCount}";
                } elseif ($gender === 'female') {
                    $femaleCount++;
                    $identifier = "Female {$femaleCount}";
                } else {
                    $identifier = 'Character';
                }
                $userPrompt .= "- {$character->name} ({$identifier})\n";
            }
            $userPrompt .= "\nScene prompt:\n\"{$scenePrompt}\"\n\n";
            $userPrompt .= 'Which characters from the list above are present in this scene? Return JSON: {"characters": ["name1", "name2"]}';

            $this->chatService->addSystemMessage($systemPrompt);
            $this->chatService->addUserMessage($userPrompt);

            $result = $this->chatService->chat();

            if ($trackingContext) {
                $this->chatService->trackRequestLog(
                    $trackingContext['book_id'],
                    $trackingContext['chapter_id'],
                    $trackingContext['user_id'],
                    'scene_character_identification',
                    $result,
                    $trackingContext['profile_id'] ?? null
                );
            }

            if (empty($result['completion'])) {
                return [];
            }

            $parsed = json_decode($result['completion'], true);

            if (json_last_error() !== JSON_ERROR_NONE || ! isset($parsed['characters'])) {
                return [];
            }

            $presentCharacters = $parsed['characters'];
            $selectedPortraits = [];

            foreach ($presentCharacters as $name) {
                if (isset($characterPortraits[$name])) {
                    $selectedPortraits[] = $characterPortraits[$name];
                }
            }

            return $selectedPortraits;
        } catch (\Throwable $e) {
            Log::error('[ImageGenerationService::getCharacterImagesForScene] Exception', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Strip quotes from a string.
     */
    protected function stripQuotes(?string $message): ?string
    {
        if (! $message) {
            return null;
        }

        $message = ltrim($message, '"');
        $message = rtrim($message, '"');

        return $message;
    }

    /**
     * Check if a string is valid JSON.
     */
    protected function isJsonString(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        // Quick check: must start with { or [
        $trimmed = trim($string);
        if (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
