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

        $book = $this->bookService->getById($bookId, ['*'], ['characters', 'chapters']);

        if (! $book) {
            Log::error('BookCoverService: Book not found', ['book_id' => $bookId]);

            return false;
        }

        // Get or create Image record
        $image = $this->imageService->getOrCreateBookCover($book);
        $this->imageService->markProcessing($image);

        try {
            // Generate cover scene JSON using AI with Flux 2 schema
            $coverSceneData = $this->generateCoverSceneJson($book);

            if (! $coverSceneData) {
                $this->imageService->markError($image, 'Failed to generate cover scene data');
                event(new ImageGeneratedEvent($image->fresh()));

                return false;
            }

            Log::info('BookCoverService: Cover scene data generated', [
                'has_scene' => ! empty($coverSceneData['scene']),
                'subjects_count' => count($coverSceneData['subjects'] ?? []),
                'characters_identified' => $coverSceneData['identified_characters'] ?? [],
            ]);

            // Build the JSON prompt for Flux 2
            $coverImagePrompt = json_encode($coverSceneData['prompt_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Update Image record with prompt
            $this->imageService->updateById($image->id, ['prompt' => $coverSceneData['scene']]);

            // Generate cover image using Replicate Flux 2 with identified character portraits
            $coverImagePath = $this->generateCoverImage($book, $coverImagePrompt, $coverSceneData['character_portraits']);

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
     * Generate cover scene data using AI with Flux 2 JSON schema.
     *
     * @return array{scene: string, prompt_data: array, identified_characters: array<string>, character_portraits: array<string>}|null
     */
    protected function generateCoverSceneJson(Book $book): ?array
    {
        $characters = $book->characters ?? collect();

        // Build character portrait map (name => portrait URL)
        $characterPortraits = [];
        $characterList = [];
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

            $portraitUrl = $character->portrait_image_url;
            if ($portraitUrl) {
                $characterPortraits[$character->name] = $portraitUrl;
            }

            $desc = [];
            if ($character->age) {
                $desc[] = "{$character->age} years old";
            }
            if ($character->gender) {
                $desc[] = $character->gender;
            }
            if ($character->nationality) {
                $desc[] = $character->nationality;
            }
            if ($character->description) {
                $desc[] = $character->description;
            }

            $characterList[] = [
                'name' => $character->name,
                'identifier' => $identifier,
                'description' => implode(', ', $desc),
                'has_portrait' => ! empty($portraitUrl),
            ];
        }

        Log::info('BookCoverService: Character portraits available', [
            'total_characters' => count($characterList),
            'characters_with_portraits' => count($characterPortraits),
            'portrait_names' => array_keys($characterPortraits),
        ]);

        // Get story context from chapters if available
        $storyContext = $this->getStoryContextForCover($book);

        // Use AI to generate the cover scene and identify characters
        $sceneData = $this->generateCoverSceneWithAi($book, $characterList, $storyContext);

        if (! $sceneData) {
            return null;
        }

        // Get only the portraits for identified characters
        $selectedPortraits = [];
        foreach ($sceneData['characters'] as $characterName) {
            if (isset($characterPortraits[$characterName])) {
                $selectedPortraits[] = $characterPortraits[$characterName];
                Log::debug('BookCoverService: Including portrait for character', [
                    'character' => $characterName,
                ]);
            }
        }

        // Build the Flux 2 JSON prompt structure
        $styleConfig = $this->getFlux2StyleConfig($book);
        $subjects = $this->buildSubjectsFromSceneData($sceneData, $characters);

        $promptData = [
            'scene' => $sceneData['scene'],
            'subjects' => $subjects,
            'style' => $styleConfig['style'],
            'color_palette' => $styleConfig['color_palette'],
            'lighting' => $styleConfig['lighting'],
            'mood' => $styleConfig['mood'],
            'background' => $sceneData['background'] ?? 'Contextual background matching the story setting',
            'composition' => '3:4 portrait, book cover composition, dramatic focal point, balanced composition',
            'camera' => [
                'angle' => $styleConfig['camera_angle'],
                'lens' => $styleConfig['lens'],
                'depth_of_field' => $styleConfig['depth_of_field'],
            ],
            'constraints' => [
                'NO TEXT',
                'NO LETTERS',
                'NO WORDS',
                'NO WRITING',
                'NO TITLES',
                'Clean image without any text overlay',
            ],
        ];

        Log::info('BookCoverService: Built Flux 2 prompt data', [
            'subjects_count' => count($subjects),
            'identified_characters' => $sceneData['characters'],
            'selected_portraits_count' => count($selectedPortraits),
        ]);

        return [
            'scene' => $sceneData['scene'],
            'prompt_data' => $promptData,
            'identified_characters' => $sceneData['characters'],
            'character_portraits' => $selectedPortraits,
        ];
    }

    /**
     * Get story context from chapters for cover generation.
     */
    protected function getStoryContextForCover(Book $book): string
    {
        $context = "Title: {$book->title}\n";
        $context .= "Genre: {$book->genre}\n";
        $context .= "Type: {$book->type}\n";
        $context .= "Age Level: {$book->age_level} years old\n";
        $context .= "Plot: {$book->plot}\n";

        if ($book->scene) {
            $context .= "Setting: {$book->scene}\n";
        }

        // Get summaries from completed chapters for additional context
        $chapters = $book->chapters ?? collect();
        $completedChapters = $chapters->where('status', 'complete');

        if ($completedChapters->count() > 0) {
            $context .= "\nStory Progress:\n";

            // Get first chapter summary for setup
            $firstChapter = $completedChapters->first();
            if ($firstChapter && $firstChapter->summary) {
                $context .= "Beginning: {$firstChapter->summary}\n";
            }

            // Get latest chapter summary for current state
            $lastChapter = $completedChapters->last();
            if ($lastChapter && $lastChapter->summary && $lastChapter->id !== $firstChapter->id) {
                $context .= "Latest development: {$lastChapter->summary}\n";
            }

            // Get book summary if available
            if ($lastChapter && $lastChapter->book_summary) {
                $context .= "Overall summary: {$lastChapter->book_summary}\n";
            }
        }

        return $context;
    }

    /**
     * Use AI to generate cover scene description and identify which characters to include.
     *
     * @param  array<array{name: string, identifier: string, description: string, has_portrait: bool}>  $characterList
     * @return array{scene: string, characters: array<string>, background: string, mood: string}|null
     */
    protected function generateCoverSceneWithAi(Book $book, array $characterList, string $storyContext): ?array
    {
        $ageGroup = match (true) {
            $book->age_level <= 4 => 'toddler',
            $book->age_level <= 10 => 'children',
            $book->age_level <= 18 => 'teenage',
            default => 'adult',
        };

        $this->chatService->resetMessages();
        $this->chatService->setTemperature(0.7);
        $this->chatService->setResponseFormat('json_object');
        $this->chatService->setMaxTokens(1500);

        $systemPrompt = <<<PROMPT
You are a creative director designing a book cover for a {$ageGroup} {$book->genre} story.

Your task is to:
1. Design a visually striking SCENE that captures the essence of the story
2. Identify which characters from the list should appear on the cover (1-3 characters maximum)
3. Describe the scene in detail for an image generation AI

IMPORTANT RULES:
- Design a SCENE, not a book cover layout
- DO NOT include any text, titles, or words in the scene description
- Focus on: characters, setting, mood, lighting, colors, and atmosphere
- Characters should be identified by their gender identifier (e.g., "Male 1", "Female 2"), NOT by name
- Only include characters that are central to the story's main conflict or theme
- Prefer characters that have portrait images available for better consistency

CHARACTER IDENTIFICATION:
- Use "[Gender] [Number]" format (e.g., "Male 1", "Female 1")
- Describe each character's position and action in the scene
- Include their physical description from the provided character data

STYLE GUIDANCE:
- For ages 13 and under: Whimsical cartoon illustration style
- For ages 14+: Photorealistic, cinematic style
PROMPT;

        $characterInfo = "Available characters (in order):\n";
        foreach ($characterList as $char) {
            $portraitNote = $char['has_portrait'] ? ' [HAS PORTRAIT]' : '';
            $characterInfo .= "- {$char['name']} ({$char['identifier']}): {$char['description']}{$portraitNote}\n";
        }

        $userPrompt = <<<PROMPT
Story Context:
{$storyContext}

{$characterInfo}

Generate a book cover scene. Respond with a JSON object containing:
{
    "scene": "A detailed visual description of the cover scene (2-3 paragraphs). Use character identifiers like 'Male 1', 'Female 2' instead of names. Describe positioning, actions, expressions, and interactions.",
    "characters": ["Character Name 1", "Character Name 2"], // Array of character NAMES (not identifiers) to include - maximum 3
    "background": "Description of the background setting and environment",
    "mood": "The emotional tone and atmosphere of the scene",
    "key_elements": ["element1", "element2"] // 3-5 key visual elements that should be prominent
}
PROMPT;

        $this->chatService->addSystemMessage($systemPrompt);
        $this->chatService->addUserMessage($userPrompt);

        Log::info('BookCoverService: Requesting AI cover scene generation');

        $result = $this->chatService->chat();

        $this->chatService->trackRequestLog(
            $book->id,
            '0',
            $book->user_id,
            'book_cover_scene',
            $result,
            $book->profile_id
        );

        if (empty($result['completion'])) {
            Log::error('BookCoverService: AI request failed for cover scene', [
                'error' => $result['error'] ?? 'Empty completion',
            ]);

            return null;
        }

        $parsed = json_decode($result['completion'], true);

        if (json_last_error() !== JSON_ERROR_NONE || ! isset($parsed['scene'])) {
            Log::error('BookCoverService: Failed to parse AI cover scene response', [
                'error' => json_last_error_msg(),
                'response' => $result['completion'],
            ]);

            return null;
        }

        Log::info('BookCoverService: AI cover scene generated', [
            'scene_length' => strlen($parsed['scene']),
            'characters_identified' => $parsed['characters'] ?? [],
            'mood' => $parsed['mood'] ?? 'not specified',
        ]);

        return [
            'scene' => $parsed['scene'],
            'characters' => $parsed['characters'] ?? [],
            'background' => $parsed['background'] ?? '',
            'mood' => $parsed['mood'] ?? '',
        ];
    }

    /**
     * Build subjects array from AI-generated scene data and character information.
     *
     * @param  array{scene: string, characters: array<string>, background: string, mood: string}  $sceneData
     * @param  \Illuminate\Support\Collection  $characters
     * @return array<array{description: string, position: string, action: string}>
     */
    protected function buildSubjectsFromSceneData(array $sceneData, $characters): array
    {
        $subjects = [];
        $scene = strtolower($sceneData['scene']);

        // Build character map for quick lookup
        $characterMap = [];
        $maleCount = 0;
        $femaleCount = 0;

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

            $characterMap[$character->name] = [
                'identifier' => $identifier,
                'full_description' => implode(', ', $description),
            ];
        }

        // Build subjects from identified characters
        $positions = ['left side of frame', 'center of frame', 'right side of frame'];

        foreach ($sceneData['characters'] as $index => $characterName) {
            if (isset($characterMap[$characterName])) {
                $charData = $characterMap[$characterName];

                // Try to extract action from scene description
                $action = $this->extractActionFromScene($sceneData['scene'], $charData['identifier']);

                $subjects[] = [
                    'description' => $charData['full_description'] ?: "A {$charData['identifier']} character",
                    'position' => $positions[$index % count($positions)],
                    'action' => $action ?: 'present in scene',
                ];
            }
        }

        // If no subjects found, create a generic one
        if (empty($subjects)) {
            $subjects[] = [
                'description' => 'Main subject of the story',
                'position' => 'center of frame',
                'action' => 'as described in scene',
            ];
        }

        return $subjects;
    }

    /**
     * Extract action description for a character from the scene.
     */
    protected function extractActionFromScene(string $scene, string $characterIdentifier): string
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

        $sceneLower = strtolower($scene);

        foreach ($actionVerbs as $verb) {
            if (str_contains($sceneLower, $verb)) {
                return ucfirst($verb);
            }
        }

        return 'engaged in the scene';
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
            $baseStyle = 'Whimsical cartoon illustration in the style of Neil Gaiman and Dave McKean, hand-drawn aesthetic, rich textures, imaginative and dreamlike, vibrant colors';
            $defaultPalette = ['#FFB347', '#87CEEB', '#98D8AA', '#F5E6CC', '#E8B4BC'];
            $defaultLighting = 'Warm, soft lighting with gentle shadows, storybook quality';
            $defaultCameraAngle = 'eye-level or slightly low angle for wonder';
            $defaultLens = 'standard lens, natural perspective';
            $defaultDepthOfField = 'moderate depth, all elements visible';
        } else {
            $baseStyle = 'Photorealistic digital art, cinematic lighting, highly detailed, professional quality, dramatic composition';
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
            'historical' => [
                'mood' => 'authentic, period-accurate, rich historical atmosphere',
                'palette' => $isChildFriendly
                    ? ['#D4AC0D', '#873600', '#1E8449', '#6E2C00', '#B7950B']
                    : ['#7E5109', '#6E2C00', '#145A32', '#4A235A', '#7D6608'],
                'lighting' => 'Natural period-appropriate lighting, warm tones',
            ],
            'comedy' => [
                'mood' => 'fun, lighthearted, playful energy, expressive',
                'palette' => ['#FF6B6B', '#4ECDC4', '#FFE66D', '#95E1D3', '#F38181'],
                'lighting' => 'Bright, cheerful lighting with vibrant highlights',
            ],
            'animal_stories' => [
                'mood' => 'heartwarming, expressive animals, natural setting',
                'palette' => ['#81C784', '#A5D6A7', '#8D6E63', '#FFCC80', '#90CAF9'],
                'lighting' => 'Warm natural lighting, golden hour quality',
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
     * Generate cover image using Replicate Flux 2 with JSON prompt and character portraits.
     *
     * @param  array<string>  $characterPortraits  URLs of character portraits to use as reference
     */
    protected function generateCoverImage(Book $book, string $jsonPrompt, array $characterPortraits): ?string
    {
        if (empty($jsonPrompt)) {
            Log::warning('BookCoverService: Empty cover image prompt');

            return null;
        }

        Log::info('BookCoverService: Generating image with Replicate', [
            'prompt_length' => strlen($jsonPrompt),
            'character_portraits_count' => count($characterPortraits),
            'is_json_prompt' => true,
        ]);

        $trackingContext = [
            'item_type' => 'book_cover',
            'user_id' => $book->user_id,
            'profile_id' => $book->profile_id,
            'book_id' => $book->id,
        ];

        $result = $this->replicateService->generateImage(
            $jsonPrompt,
            $characterPortraits,
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
}
