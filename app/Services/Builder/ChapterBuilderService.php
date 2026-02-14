<?php

namespace App\Services\Builder;

use App\Jobs\Chapter\CreateChapterInlineImagesJob;
use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChapterBuilderService extends BuilderService
{
    public function buildChapter(string $bookId, array $data)
    {
        $startTime = microtime(true);

        Log::info('[ChapterBuilderService::buildChapter] Starting chapter build', [
            'book_id' => $bookId,
            'data_keys' => array_keys($data),
            'has_first_chapter_prompt' => ! empty($data['first_chapter_prompt'] ?? null),
            'is_final_chapter' => $data['final_chapter'] ?? false,
        ]);

        try {
            Log::debug('[ChapterBuilderService::buildChapter] Fetching book with chapters and user');
            $book = $this->bookService->getById($bookId, null, ['with' => ['chapters', 'user']]);

            if (! $book) {
                Log::error('[ChapterBuilderService::buildChapter] Book not found', ['book_id' => $bookId]);
                throw new \RuntimeException("Book not found: {$bookId}");
            }

            Log::debug('[ChapterBuilderService::buildChapter] Book fetched', [
                'book_id' => $book->id,
                'book_type' => $book->type,
                'book_genre' => $book->genre,
                'existing_chapters_count' => $book->chapters->count(),
                'complete_chapters_count' => $book->chapters->where('status', 'complete')->count(),
            ]);

            $user = Auth::user();
            $profileId = session('current_profile_id');

            // Fall back to book's user when running in a job context (no authenticated user)
            if (! $user && $book->user_id) {
                $user = $book->user;
                $profileId = $profileId ?? $book->profile_id;
                Log::debug('[ChapterBuilderService::buildChapter] Using book owner as user (job context)', [
                    'user_id' => $user?->id ?? 'null',
                    'profile_id' => $profileId ?? 'null',
                ]);
            }

            Log::debug('[ChapterBuilderService::buildChapter] Auth context', [
                'user_id' => $user?->id ?? 'null',
                'profile_id' => $profileId ?? 'null',
                'source' => Auth::check() ? 'auth' : 'book_owner',
            ]);

            if (! $user) {
                Log::error('[ChapterBuilderService::buildChapter] No authenticated user found and book has no owner');
                throw new \RuntimeException('No authenticated user found when creating chapter');
            }

            $chapterData = [
                'book_id' => $book->id,
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'sort' => $book->chapters->where('status', 'complete')->count() + 1,
            ];

            Log::info('[ChapterBuilderService::buildChapter] Creating chapter record', $chapterData);

            $chapter = $this->chapterService->store($chapterData, ['events' => false]);

            Log::info('[ChapterBuilderService::buildChapter] Chapter record created', [
                'chapter_id' => $chapter->id,
                'chapter_sort' => $chapter->sort,
            ]);

            Log::debug('[ChapterBuilderService::buildChapter] Calling getCombinedChapterData');
            $combinedStartTime = microtime(true);
            $chapterContent = $this->getCombinedChapterData($bookId, $data, $chapter->id);
            $combinedDuration = microtime(true) - $combinedStartTime;

            Log::info('[ChapterBuilderService::buildChapter] Combined chapter data generated', [
                'chapter_id' => $chapter->id,
                'has_body' => ! empty($chapterContent['body']),
                'has_title' => ! empty($chapterContent['title']),
                'has_summary' => ! empty($chapterContent['summary']),
                'has_image_prompt' => ! empty($chapterContent['image_prompt']),
                'body_length' => strlen($chapterContent['body'] ?? ''),
                'duration_seconds' => round($combinedDuration, 2),
            ]);

            if (empty($chapterContent['body'])) {
                Log::error('[ChapterBuilderService::buildChapter] Empty body returned', [
                    'chapter_id' => $chapter->id,
                    'chapter_content' => $chapterContent,
                ]);
                throw new \RuntimeException('Empty chapter body returned from AI');
            }

            $data['body'] = $chapterContent['body'];
            $data['summary'] = $chapterContent['summary'] ?? '';
            $data['title'] = $chapterContent['title'] ?? null;
            $data['status'] = 'complete';

            $sceneImages = $chapterContent['scene_images'] ?? [];
            $imagePrompt = $chapterContent['image_prompt'] ?? null;

            // Create header image record BEFORE updating chapter so the event listener can dispatch the job
            $headerImage = null;
            if (! empty($imagePrompt)) {
                $imageService = app(\App\Services\Image\ImageService::class);
                $headerImage = $imageService->createChapterHeaderImage($chapter, $imagePrompt);
                $data['header_image_id'] = $headerImage->id;

                Log::info('[ChapterBuilderService::buildChapter] Created header image record', [
                    'chapter_id' => $chapter->id,
                    'image_id' => $headerImage->id,
                    'prompt_preview' => substr($imagePrompt, 0, 100),
                ]);
            }

            Log::info('[ChapterBuilderService::buildChapter] Updating chapter with final data', [
                'chapter_id' => $chapter->id,
                'status' => 'complete',
                'summary_length' => strlen($data['summary']),
                'body_length' => strlen($data['body']),
                'has_title' => ! empty($data['title']),
                'has_image_prompt' => ! empty($imagePrompt),
                'has_header_image_id' => ! empty($data['header_image_id']),
                'scene_images_count' => count($sceneImages),
            ]);

            // This update triggers ChapterUpdatedEvent which fires CreateChapterImageListener
            // The listener will dispatch CreateChapterImageJob since header_image_id is now set
            $chapter = $this->chapterService->updateById($chapter->id, $data);

            // Dispatch inline images generation as a background job after chapter is saved
            if (! empty($sceneImages) && is_array($sceneImages)) {
                Log::info('[ChapterBuilderService::buildChapter] Dispatching inline images job', [
                    'chapter_id' => $chapter->id,
                    'scene_count' => count($sceneImages),
                ]);

                CreateChapterInlineImagesJob::dispatch($chapter->fresh(), $sceneImages)->onQueue('images');
            }

            $endTime = microtime(true);
            $totalDuration = $endTime - $startTime;

            Log::info('[ChapterBuilderService::buildChapter] Chapter build completed successfully', [
                'book_id' => $bookId,
                'chapter_id' => $chapter->id,
                'total_duration_seconds' => round($totalDuration, 2),
                'combined_generation_seconds' => round($combinedDuration, 2),
            ]);

            return $chapter;
        } catch (Throwable $e) {
            $endTime = microtime(true);

            Log::error('[ChapterBuilderService::buildChapter] Exception thrown', [
                'book_id' => $bookId,
                'duration_seconds' => round($endTime - $startTime, 2),
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    public function getCombinedChapterData(string $bookId, array $data, ?string $chapterId = null): array
    {
        Log::debug('[ChapterBuilderService::getCombinedChapterData] Starting', [
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
        ]);

        try {
            $book = $this->bookService->getById($bookId, null, ['with' => ['chapters', 'characters']]);

            if ($book->type == 'theatre') {
                $chapterLabel = 'scene';
                $bookTypeLabel = 'theatre play script';
            } elseif ($book->type == 'screenplay') {
                $chapterLabel = 'scene';
                $bookTypeLabel = 'screenplay';
            } else {
                $chapterLabel = 'chapter';
                $bookTypeLabel = 'chapter book';
            }

            $isScriptFormat = in_array($book->type, ['theatre', 'screenplay']);

            $chapters = $book->chapters->where('status', 'complete');
            $completedChaptersCount = $chapters->count();
            $finalChapter = $data['final_chapter'] ?? false;

            $userAddedPrompt = '';
            if ($userPromptText = $data['user_prompt'] ?? null) {
                $userAddedPrompt = ' Ensure that it includes: '.$userPromptText;
            }

            $targetWordCount = $this->getBodyWordCount($book->id);
            $minimumWordCount = (int) round($targetWordCount * 0.75);

            $systemPrompt = [
                'you_are' => 'An author writing the next '.$chapterLabel.' in a '.$book->genre.' '.$bookTypeLabel.' for '.$book->age_level.' year old readers.',
                'story_details' => [
                    'format' => $bookTypeLabel,
                    'genre' => $book->genre,
                    'plot' => $book->plot,
                ],
                'rules' => [
                    'Ensure narrative flow and rich, descriptive storytelling.',
                    'The '.$chapterLabel.' body MUST be between '.$minimumWordCount.' and '.$targetWordCount.' words. Aim for '.$targetWordCount.' words.',
                    'No '.$chapterLabel.' number or title in the body.',
                    'The characters must not be in more than 1 physical location.',
                    'The body text MUST use paragraph breaks. Separate each paragraph with \\n\\n (two newlines). Every '.$chapterLabel.' should have multiple paragraphs.',
                    'Write vivid, immersive prose with dialogue, description, and action.',
                ],
            ];

            if ($isScriptFormat) {
                $scriptRules = $this->getScriptFormattingRules($book->type);
                $systemPrompt['rules'] = array_merge($systemPrompt['rules'], $scriptRules);
            }

            if ($book->user_characters) {
                $systemPrompt['story_details']['main_characters'] = $book->user_characters;
            }

            // Add character descriptions for scene image generation
            $characters = $book->characters()->get();
            if ($characters->count() > 0) {
                $characterDescriptions = [];
                foreach ($characters as $character) {
                    $desc = $character->name;
                    if ($character->age) {
                        $desc .= " ({$character->age} years old)";
                    }
                    if ($character->gender) {
                        $desc .= ", {$character->gender}";
                    }
                    if ($character->description) {
                        $desc .= ": {$character->description}";
                    }
                    $characterDescriptions[] = $desc;
                }
                $systemPrompt['story_details']['characters'] = $characterDescriptions;
            }

            $userPrompt = '';
            if ($completedChaptersCount == 0) {
                $userPrompt = 'Write the first '.$chapterLabel.' of this story.';

                if (array_key_exists('first_chapter_prompt', $data) && $data['first_chapter_prompt'] != '') {
                    $userPrompt .= ' about: '.$data['first_chapter_prompt'];
                }

            } else {
                $previousChapter = $chapters->last();

                if ($previousChapter->book_summary) {
                    $systemPrompt['story_details']['summary_so_far'] = $previousChapter->book_summary;
                }

                if ($previousChapter->summary) {
                    $systemPrompt['story_details']['previous_'.$chapterLabel.'_summary'] = $previousChapter->summary;
                }

                if ($finalChapter == 1) {
                    $userPrompt = 'Write the final '.$chapterLabel.' to this '.$bookTypeLabel.'.';
                } else {
                    $userPrompt = 'Write the next '.$chapterLabel.' to this '.$bookTypeLabel.'.';
                }
            }

            $this->chatService->resetMessages();
            $this->chatService->setResponseFormat('json_object');
            $this->chatService->setMaxTokens(8000);
            $this->chatService->addSystemMessage(json_encode($systemPrompt));

            $characterInstructions = $this->getCharacterIdentificationInstructions($characters);

            $physicalPresenceRule = 'CRITICAL: ONLY include characters who are PHYSICALLY PRESENT at the scene location - do NOT include characters who are merely mentioned, remembered, or talked about but not bodily there. ';

            $imageFormatHint = $this->isFlux2Model() ? '16:9 landscape format.' : '9:16 portrait format.';

            $outputFormat = [
                'body' => 'The full '.$chapterLabel.' text ('.$minimumWordCount.'-'.$targetWordCount.' words). MUST contain multiple paragraphs separated by \\n\\n (two newlines). Write rich, descriptive prose.',
                'title' => 'A compelling '.$chapterLabel.' title (plain text, no '.$chapterLabel.' number)',
                'summary' => 'A detailed summary with key events, character names, descriptions, ages, genders, experiences, thoughts, goals, and nationalities. No commentary.',
                'image_prompt' => 'A detailed one-sentence prompt for an image generation service describing a key scene from this '.$chapterLabel.'. '.$physicalPresenceRule.$characterInstructions.' Describe the visual scene, setting, mood, and action.',
                'scene_images' => [
                    [
                        'paragraph_index' => 'The 0-based paragraph index where this scene occurs (early in the '.$chapterLabel.', around 20-30% through)',
                        'prompt' => 'A detailed visual prompt for an image generation AI describing this specific scene. '.$physicalPresenceRule.$characterInstructions.' Include setting details, lighting, mood, and action. '.$imageFormatHint,
                    ],
                    [
                        'paragraph_index' => 'The 0-based paragraph index where this scene occurs (later in the '.$chapterLabel.', around 60-80% through)',
                        'prompt' => 'A detailed visual prompt for an image generation AI describing this specific scene. '.$physicalPresenceRule.$characterInstructions.' Include setting details, lighting, mood, and action. '.$imageFormatHint,
                    ],
                ],
            ];

            $combinedPrompt = $userPrompt.' '.$userAddedPrompt;
            $combinedPrompt .= ' Respond with a JSON object containing: '.json_encode($outputFormat);

            $this->chatService->addUserMessage($combinedPrompt);

            Log::info('[ChapterBuilderService::getCombinedChapterData] Calling chat service');
            $chatStartTime = microtime(true);
            $result = $this->chatService->chat();
            $chatDuration = microtime(true) - $chatStartTime;

            Log::info('[ChapterBuilderService::getCombinedChapterData] Chat service returned', [
                'duration_seconds' => round($chatDuration, 2),
                'result_type' => gettype($result),
                'has_completion' => is_array($result) && isset($result['completion']),
            ]);

            $this->chatService->trackRequestLog(
                $bookId,
                $chapterId ?? '0',
                $book->user_id,
                'chapter_content',
                $result,
                $book->profile_id
            );

            if (empty($result['completion'])) {
                Log::error('[ChapterBuilderService::getCombinedChapterData] Empty completion', [
                    'result' => $result,
                ]);
                throw new \RuntimeException('Empty response from AI service');
            }

            $jsonData = $this->parseJsonResponse($result['completion']);

            if ($jsonData === null) {
                Log::error('[ChapterBuilderService::getCombinedChapterData] JSON decode error', [
                    'error' => json_last_error_msg(),
                    'completion' => $result['completion'],
                ]);
                throw new \RuntimeException('Failed to decode JSON response: '.json_last_error_msg());
            }

            Log::debug('[ChapterBuilderService::getCombinedChapterData] Successfully parsed JSON', [
                'has_body' => isset($jsonData['body']),
                'has_title' => isset($jsonData['title']),
                'has_summary' => isset($jsonData['summary']),
                'has_image_prompt' => isset($jsonData['image_prompt']),
                'has_scene_images' => isset($jsonData['scene_images']),
                'scene_images_count' => is_array($jsonData['scene_images'] ?? null) ? count($jsonData['scene_images']) : 0,
            ]);

            $body = $this->stripQuotes($jsonData['body'] ?? '') ?? '';
            $body = $this->normalizeBodyParagraphs($body);

            return [
                'body' => $body,
                'title' => $this->stripQuotes($jsonData['title'] ?? ''),
                'summary' => $this->stripQuotes($jsonData['summary'] ?? ''),
                'image_prompt' => $this->stripQuotes($jsonData['image_prompt'] ?? ''),
                'scene_images' => $jsonData['scene_images'] ?? [],
            ];
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::getCombinedChapterData] Exception', [
                'book_id' => $bookId,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate inline images for a chapter based on scene prompts.
     *
     * @param  string  $bookId  The book ID for style reference
     * @param  string  $chapterId  The chapter ID for image storage
     * @param  array<array{paragraph_index: int|string, prompt: string}>  $scenePrompts  Scene image prompts from AI
     * @return array<array{paragraph_index: int, url: string, prompt: string}> Generated images with positions
     */
    public function generateChapterImages(string $bookId, string $chapterId, array $scenePrompts): array
    {
        Log::info('[ChapterBuilderService::generateChapterImages] Starting image generation', [
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'scene_count' => count($scenePrompts),
        ]);

        $book = $this->bookService->getById($bookId, null, ['with' => ['characters', 'chapters']]);
        $isFlux2 = $this->isFlux2Model();

        Log::debug('[ChapterBuilderService::generateChapterImages] Model check', [
            'is_flux_2' => $isFlux2,
        ]);

        // Build character portrait map (name => portrait URL)
        $characterPortraits = [];
        if ($book->characters) {
            foreach ($book->characters as $character) {
                $portraitUrl = $character->portrait_image_url;
                if ($portraitUrl) {
                    $characterPortraits[$character->name] = $portraitUrl;
                }
            }
        }

        Log::info('[ChapterBuilderService::generateChapterImages] Reference images collected', [
            'available_character_portraits' => array_keys($characterPortraits),
        ]);

        $inlineImages = [];

        foreach ($scenePrompts as $index => $scene) {
            if (empty($scene['prompt'])) {
                Log::warning('[ChapterBuilderService::generateChapterImages] Empty prompt for scene', [
                    'scene_index' => $index,
                ]);

                continue;
            }

            $paragraphIndex = is_numeric($scene['paragraph_index'])
                ? (int) $scene['paragraph_index']
                : $index * 2;

            // Build prompt based on model type
            if ($isFlux2) {
                $fullPrompt = $this->buildFlux2JsonPrompt($book, $scene['prompt']);
            } else {
                $style = $this->getSceneImageStylePrefix($book);
                $fullPrompt = trim($style.' '.$this->stripQuotes($scene['prompt']));
            }

            // Identify which characters are present in this scene and get their portraits
            $inputImages = $this->getCharacterImagesForScene(
                $scene['prompt'],
                $book->characters,
                $characterPortraits,
                [
                    'book_id' => $bookId,
                    'chapter_id' => $chapterId,
                    'user_id' => $book->user_id,
                    'profile_id' => $book->profile_id,
                ]
            );

            Log::debug('[ChapterBuilderService::generateChapterImages] Generating image', [
                'scene_index' => $index,
                'paragraph_index' => $paragraphIndex,
                'prompt_preview' => substr($fullPrompt, 0, 200),
                'is_json_prompt' => $isFlux2,
                'character_images_count' => count($inputImages),
            ]);

            try {
                $trackingContext = [
                    'item_type' => 'chapter_inline_image',
                    'user_id' => $book->user_id,
                    'profile_id' => $book->profile_id,
                    'book_id' => $bookId,
                    'chapter_id' => $chapterId,
                ];

                $imageResponse = $this->replicateApiService->generateImage(
                    $fullPrompt,
                    $inputImages,
                    $this->getEffectiveAspectRatio('16:9'),
                    $trackingContext
                );

                if (! $imageResponse || ! isset($imageResponse['url']) || ! $imageResponse['url']) {
                    Log::warning('[ChapterBuilderService::generateChapterImages] No image URL returned', [
                        'scene_index' => $index,
                        'response' => $imageResponse,
                    ]);

                    continue;
                }

                $s3Url = $this->saveImageToS3(
                    $imageResponse['url'],
                    'chapters/inline',
                    $chapterId.'_scene_'.$index
                );

                if ($s3Url) {
                    $inlineImages[] = [
                        'paragraph_index' => $paragraphIndex,
                        'url' => $s3Url,
                        'prompt' => $fullPrompt,
                    ];

                    Log::info('[ChapterBuilderService::generateChapterImages] Image generated and saved', [
                        'scene_index' => $index,
                        'paragraph_index' => $paragraphIndex,
                        'url' => $s3Url,
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('[ChapterBuilderService::generateChapterImages] Image generation failed', [
                    'scene_index' => $index,
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[ChapterBuilderService::generateChapterImages] Image generation complete', [
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'images_generated' => count($inlineImages),
        ]);

        return $inlineImages;
    }

    /**
     * Generate a chapter header image using the same FLUX 2 schema and character identification
     * as inline chapter images.
     *
     * @param  string  $bookId  The book ID
     * @param  string  $chapterId  The chapter ID
     * @param  string  $prompt  The image prompt describing the scene
     * @return array{image_prompt: string, image: string|null}
     */
    public function generateHeaderImage(string $bookId, string $chapterId, string $prompt): array
    {
        Log::info('[ChapterBuilderService::generateHeaderImage] Starting header image generation', [
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'prompt_preview' => substr($prompt, 0, 200),
        ]);

        $book = $this->bookService->getById($bookId, null, ['with' => ['characters', 'chapters']]);
        $isFlux2 = $this->isFlux2Model();

        Log::debug('[ChapterBuilderService::generateHeaderImage] Model check', [
            'is_flux_2' => $isFlux2,
        ]);

        // Build character portrait map (name => portrait URL)
        $characterPortraits = [];
        if ($book->characters) {
            foreach ($book->characters as $character) {
                $portraitUrl = $character->portrait_image_url;
                if ($portraitUrl) {
                    $characterPortraits[$character->name] = $portraitUrl;
                }
            }
        }

        Log::info('[ChapterBuilderService::generateHeaderImage] Reference images collected', [
            'available_character_portraits' => array_keys($characterPortraits),
        ]);

        // Build prompt based on model type
        if ($isFlux2) {
            $fullPrompt = $this->buildFlux2JsonPrompt($book, $prompt);
        } else {
            $style = $this->getSceneImageStylePrefix($book);
            $fullPrompt = trim($style.' '.$this->stripQuotes($prompt));
        }

        // Identify which characters are present in this scene and get their portraits
        $inputImages = $this->getCharacterImagesForScene(
            $prompt,
            $book->characters,
            $characterPortraits,
            [
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'user_id' => $book->user_id,
                'profile_id' => $book->profile_id,
            ]
        );

        Log::debug('[ChapterBuilderService::generateHeaderImage] Generating image', [
            'prompt_preview' => substr($fullPrompt, 0, 200),
            'is_json_prompt' => $isFlux2,
            'character_images_count' => count($inputImages),
        ]);

        try {
            $trackingContext = [
                'item_type' => 'chapter_header_image',
                'user_id' => $book->user_id,
                'profile_id' => $book->profile_id,
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
            ];

            $imageResponse = $this->replicateApiService->generateImage(
                $fullPrompt,
                $inputImages,
                $this->getEffectiveAspectRatio('16:9'),
                $trackingContext
            );

            if (! $imageResponse || ! isset($imageResponse['url']) || ! $imageResponse['url']) {
                Log::warning('[ChapterBuilderService::generateHeaderImage] No image URL returned', [
                    'response' => $imageResponse,
                ]);

                return [
                    'image_prompt' => $fullPrompt,
                    'image' => null,
                ];
            }

            $s3Url = $this->saveImageToS3(
                $imageResponse['url'],
                'chapters/headers',
                $chapterId.'_header'
            );

            Log::info('[ChapterBuilderService::generateHeaderImage] Image generated and saved', [
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'url' => $s3Url,
            ]);

            return [
                'image_prompt' => $fullPrompt,
                'image' => $s3Url,
            ];
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::generateHeaderImage] Image generation failed', [
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            return [
                'image_prompt' => $fullPrompt,
                'image' => null,
            ];
        }
    }

    /**
     * Check if the current image generation model is FLUX 2.
     */
    protected function isFlux2Model(): bool
    {
        $useCustomModel = (bool) config('services.replicate.use_custom_model', false);

        if ($useCustomModel) {
            return false;
        }

        return true;
    }

    /**
     * Get the effective aspect ratio for the current model.
     *
     * Custom models produce better results in 9:16 portrait orientation,
     * while Flux 2 should use 16:9 landscape.
     */
    protected function getEffectiveAspectRatio(string $aspectRatio): string
    {
        if (! $this->isFlux2Model() && $aspectRatio === '16:9') {
            return '9:16';
        }

        return $aspectRatio;
    }

    /**
     * Build a JSON-structured prompt for FLUX 2 model.
     * This ensures consistent character descriptions and artistic style across all images.
     */
    protected function buildFlux2JsonPrompt(Book $book, string $sceneDescription): string
    {
        $characters = $book->characters ?? collect();

        // Build subjects array from scene description and character data
        $subjects = $this->extractSubjectsFromScene($sceneDescription, $characters);

        // Get consistent artistic style for this book
        $styleConfig = $this->getFlux2StyleConfig($book);

        // Build the JSON prompt structure
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

        $jsonPrompt = json_encode($promptData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Log::debug('[ChapterBuilderService::buildFlux2JsonPrompt] Built JSON prompt', [
            'book_id' => $book->id,
            'subjects_count' => count($subjects),
            'style' => $styleConfig['style'],
        ]);

        return $jsonPrompt;
    }

    /**
     * Get consistent style configuration for FLUX 2 based on book attributes.
     *
     * @return array{style: string, color_palette: array<string>, lighting: string, mood: string, camera_angle: string, lens: string, depth_of_field: string}
     */
    protected function getFlux2StyleConfig(Book $book): array
    {
        // Age-based style determination
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

        // Genre-specific mood and palette adjustments
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
     * Extract subject descriptions from scene and map to character data.
     *
     * @param  \Illuminate\Support\Collection|null  $characters
     * @return array<array{description: string, position: string, action: string}>
     */
    protected function extractSubjectsFromScene(string $sceneDescription, $characters): array
    {
        $subjects = [];
        $scene = strtolower($sceneDescription);

        // Map character identifiers to their full descriptions
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

                // Build comprehensive character description
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

        // Look for character identifiers in the scene and build subject entries
        foreach ($characterMap as $identifier => $charData) {
            if (str_contains($scene, $identifier)) {
                // Try to extract action from scene description
                $action = $this->extractActionForCharacter($sceneDescription, $identifier);

                $subjects[] = [
                    'description' => $charData['full_description'] ?: "A {$identifier} character",
                    'position' => $this->estimatePositionFromScene($sceneDescription, $identifier, count($subjects)),
                    'action' => $action ?: 'present in scene',
                ];
            }
        }

        // If no character identifiers found, create a generic subject from the scene
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
        // Common background keywords to look for
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
     * Extract action description for a specific character from the scene.
     */
    protected function extractActionForCharacter(string $sceneDescription, string $characterIdentifier): string
    {
        // Action verbs to look for
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
     * Estimate character position based on scene description and order.
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

        // Default position based on subject order
        return $positions[$subjectIndex % count($positions)];
    }

    /**
     * Identify which characters are present in a scene prompt using AI.
     *
     * @param  string  $scenePrompt  The scene description prompt
     * @param  \Illuminate\Support\Collection  $characters  All characters from the book
     * @param  array<string, string>  $characterPortraits  Map of character name to portrait URL
     * @param  array{book_id: string, chapter_id: string, user_id: string, profile_id: string|null}|null  $trackingContext  Optional tracking context
     * @return array<string> Array of portrait URLs for characters present in the scene
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

            $systemPrompt = 'You identify which characters from a story are PHYSICALLY PRESENT in a scene description. ';
            $systemPrompt .= 'CRITICAL: A character is ONLY present if they are bodily in the scene location and can be seen/photographed. ';
            $systemPrompt .= 'Do NOT include characters who are: mentioned in conversation, remembered, thought about, referenced in the past, or talked about but not physically there. ';
            $systemPrompt .= 'Only include characters who would actually appear in a photograph taken at that exact moment and location. ';
            $systemPrompt .= 'Consider gender identifiers like "Male 1", "Female 1" etc. and match them to the characters based on their order. ';
            $systemPrompt .= 'Return a JSON object with a single key "characters" containing an array of character names that are PHYSICALLY present.';

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
            $userPrompt .= 'Which characters from the list above are PHYSICALLY PRESENT in this scene (bodily there, visible, can be photographed)? ';
            $userPrompt .= 'Do NOT include characters who are only mentioned, remembered, or talked about. ';
            $userPrompt .= 'Return JSON: {"characters": ["name1", "name2"]}';

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
                Log::warning('[ChapterBuilderService::getCharacterImagesForScene] Empty AI response');

                return [];
            }

            $parsed = json_decode($result['completion'], true);

            if (json_last_error() !== JSON_ERROR_NONE || ! isset($parsed['characters'])) {
                Log::warning('[ChapterBuilderService::getCharacterImagesForScene] Failed to parse AI response', [
                    'response' => $result['completion'],
                ]);

                return [];
            }

            $presentCharacters = $parsed['characters'];
            $selectedPortraits = [];

            foreach ($presentCharacters as $name) {
                if (isset($characterPortraits[$name])) {
                    $selectedPortraits[] = $characterPortraits[$name];
                }
            }

            Log::info('[ChapterBuilderService::getCharacterImagesForScene] Identified characters in scene', [
                'scene_preview' => substr($scenePrompt, 0, 100),
                'identified_characters' => $presentCharacters,
                'portraits_found' => count($selectedPortraits),
            ]);

            return $selectedPortraits;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::getCharacterImagesForScene] Exception', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get style prefix for scene images based on book genre and age level.
     * Similar to BookCoverService::getStylePrefix() but optimized for scene images.
     */
    protected function getSceneImageStylePrefix(Book $book): string
    {
        // CRITICAL: No text, letters, words, or writing on the image
        $style = '';

        // Age-based style: cartoon for kids/pre-teens, realistic for teens/adults
        if ($book->age_level <= 13) {
            $style .= 'Cartoon illustration in the style of Neil Gaiman and Dave McKean, ';
            $style .= 'whimsical yet slightly dark, hand-drawn aesthetic, rich textures, ';
            $style .= 'imaginative and dreamlike, vibrant colors with moody undertones, ';
        } else {
            $style .= 'Photorealistic, professional quality, dramatic composition, ';
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
     * Generate character identification instructions for image prompts.
     * Characters are identified by gender + number (e.g., "Male 1", "Female 2")
     * and described sequentially from left to right in the image.
     *
     * @param  \Illuminate\Support\Collection  $characters
     */
    protected function getCharacterIdentificationInstructions($characters): string
    {
        $instructions = 'CRITICAL: ONLY include characters who are PHYSICALLY PRESENT in the scene. ';
        $instructions .= 'Do NOT include characters who are merely mentioned, remembered, thought about, or talked about. ';
        $instructions .= 'A character must be bodily present at the physical location shown in the image. ';
        $instructions .= 'NO character names in the prompt. ';
        $instructions .= 'Identify each PHYSICALLY PRESENT character as "[Gender] [Number]" (e.g., "Male 1", "Female 2"). ';
        $instructions .= 'Number characters of the same gender sequentially (Male 1, Male 2, Female 1, etc.). ';
        $instructions .= 'Describe characters sequentially from LEFT to RIGHT across the image composition. ';
        $instructions .= 'Include each character\'s physical description immediately after their identifier. ';

        if ($characters->count() > 0) {
            $instructions .= 'Use the physical descriptions from story_details characters. ';

            $maleCount = 0;
            $femaleCount = 0;
            $characterMappings = [];

            foreach ($characters as $character) {
                $gender = strtolower($character->gender ?? 'unknown');

                if ($gender === 'male') {
                    $maleCount++;
                    $identifier = "Male {$maleCount}";
                } elseif ($gender === 'female') {
                    $femaleCount++;
                    $identifier = "Female {$femaleCount}";
                } else {
                    continue;
                }

                $desc = [];
                if ($character->age) {
                    $desc[] = "{$character->age} years old";
                }
                if ($character->description) {
                    $desc[] = $character->description;
                }

                if (! empty($desc)) {
                    $characterMappings[] = "{$identifier}: ".implode(', ', $desc);
                }
            }

            if (! empty($characterMappings)) {
                $instructions .= 'Character reference: '.implode('; ', $characterMappings).'. ';
            }
        }

        return $instructions;
    }

    public function getNextChapterResponse(string $bookId, array $data)
    {
        Log::debug('[ChapterBuilderService::getNextChapterResponse] Starting', [
            'book_id' => $bookId,
        ]);

        try {
            $book = $this->bookService->getById($bookId, null, ['with' => ['chapters']]);

            Log::debug('[ChapterBuilderService::getNextChapterResponse] Book type check', [
                'book_id' => $bookId,
                'book_type' => $book->type,
                'is_story_type' => $book->type == 'story',
            ]);

            if ($book->type == 'story') {
                Log::debug('[ChapterBuilderService::getNextChapterResponse] Using storybook chat flow');
                $this->setBackstory($bookId);

                return $this->getStorybookChat();
            }

            Log::debug('[ChapterBuilderService::getNextChapterResponse] Using chapter chat flow');
            $body = $this->getChapterChat($book, $data);

            $result = $this->stripQuotes($body['completion'] ?? '');

            Log::debug('[ChapterBuilderService::getNextChapterResponse] Completed', [
                'book_id' => $bookId,
                'result_length' => strlen($result),
            ]);

            return $result;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::getNextChapterResponse] Exception', [
                'book_id' => $bookId,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    protected function getStorybookChat()
    {
        Log::debug('[ChapterBuilderService::getStorybookChat] Starting');

        try {
            $this->chatService->addUserMessage('Write this book.');
            $result = $this->chatService->chat();

            Log::debug('[ChapterBuilderService::getStorybookChat] Completed', [
                'has_result' => ! empty($result),
                'result_type' => gettype($result),
            ]);

            return $result;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::getStorybookChat] Exception', [
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function getChapterChat(Book $book, ?array $data = null)
    {
        Log::debug('[ChapterBuilderService::getChapterChat] Starting', [
            'book_id' => $book->id,
            'book_type' => $book->type,
            'data_keys' => $data ? array_keys($data) : [],
        ]);

        try {
            $finalChapter = $data['final_chapter'] ?? false;

            if ($book->type == 'theatre') {
                $chapterLabel = 'scene';
                $bookTypeLabel = 'theatre play script';
            } elseif ($book->type == 'screenplay') {
                $chapterLabel = 'scene';
                $bookTypeLabel = 'screenplay';
            } else {
                $chapterLabel = 'chapter';
                $bookTypeLabel = 'chapter book';
            }

            $isScriptFormat = in_array($book->type, ['theatre', 'screenplay']);

            Log::debug('[ChapterBuilderService::getChapterChat] Labels determined', [
                'chapter_label' => $chapterLabel,
                'book_type_label' => $bookTypeLabel,
                'is_script_format' => $isScriptFormat,
            ]);

            $chapters = $book->chapters->where('status', 'complete');
            $completedChaptersCount = $chapters->count();

            Log::debug('[ChapterBuilderService::getChapterChat] Existing chapters', [
                'completed_count' => $completedChaptersCount,
            ]);

            if ($userAddedPrompt = $data['user_prompt'] ?? null) {
                $userAddedPrompt = 'Ensure that it includes: '.$userAddedPrompt;
            } else {
                $userAddedPrompt = '';
            }

            $targetWordCount = $this->getBodyWordCount($book->id);
            $minimumWordCount = (int) round($targetWordCount * 0.75);

            $userPrompt = '';
            $systemPrompt = [
                'you_are' => 'An author writing the next '.$chapterLabel.' in a '.$book->genre.' '.$bookTypeLabel.' for '.$book->age_level.' year old readers.',
                'story_details' => [
                    'format' => $bookTypeLabel,
                    'genre' => $book->genre,
                    'plot' => $book->plot,
                ],
                'rules' => [
                    'Ensure narrative flow and rich, descriptive storytelling.',
                    'The '.$chapterLabel.' body MUST be between '.$minimumWordCount.' and '.$targetWordCount.' words. Aim for '.$targetWordCount.' words.',
                    'No '.$chapterLabel.' number or title. No '.$chapterLabel.' summary at the end.',
                    'The characters must not be in more than 1 physical location.',
                    'Use paragraph breaks to structure the text. Separate each paragraph with blank lines.',
                    'Write vivid, immersive prose with dialogue, description, and action.',
                ],
            ];

            if ($isScriptFormat) {
                $scriptRules = $this->getScriptFormattingRules($book->type);
                $systemPrompt['rules'] = array_merge($systemPrompt['rules'], $scriptRules);
            }

            if ($book->user_characters) {
                $systemPrompt['story_details']['main_characters'] = $book->user_characters;
            }

            if ($completedChaptersCount == 0) {
                $userPrompt = 'Write the first '.$chapterLabel.' of this story.';

                if (array_key_exists('first_chapter_prompt', $data) && $data['first_chapter_prompt'] != '') {
                    $userPrompt .= ' about: '.$data['first_chapter_prompt'];
                }

                $systemPrompt['rules'][] = 'End the '.$chapterLabel.' with a question that makes the reader want to find out what happens next.';

                Log::debug('[ChapterBuilderService::getChapterChat] First chapter prompt built', [
                    'user_prompt' => $userPrompt,
                ]);
            } else {
                $previousChapter = $chapters->last();

                Log::debug('[ChapterBuilderService::getChapterChat] Using previous chapter context', [
                    'previous_chapter_id' => $previousChapter->id ?? 'none',
                    'has_book_summary' => ! empty($previousChapter->book_summary),
                    'has_chapter_summary' => ! empty($previousChapter->summary),
                ]);

                if ($previousChapter->book_summary) {
                    $systemPrompt['story_details']['summary_so_far'] = $previousChapter->book_summary;
                }

                if ($previousChapter->summary) {
                    $systemPrompt['story_details']['previous_'.$chapterLabel.'_summary'] = $previousChapter->summary;
                }

                if ($finalChapter == 1) {
                    $userPrompt .= 'Write the final '.$chapterLabel.' to this '.$bookTypeLabel.' .';
                } else {
                    $userPrompt .= 'Write the next '.$chapterLabel.' to this '.$bookTypeLabel.'.';
                    $systemPrompt['rules'][] = 'End the '.$chapterLabel.' with a cliffhanger that makes the reader want to find out what happens next.';
                }
            }

            Log::debug('[ChapterBuilderService::getChapterChat] System prompt prepared', [
                'system_prompt_keys' => array_keys($systemPrompt),
                'rules_count' => count($systemPrompt['rules']),
            ]);

            $this->chatService->resetMessages();
            $this->chatService->addSystemMessage(json_encode($systemPrompt));
            $this->chatService->addUserMessage($userPrompt.' '.$userAddedPrompt);

            $this->chatService->addUserMessage($userPrompt);

            Log::info('[ChapterBuilderService::getChapterChat] Calling chat service');
            $chatStartTime = microtime(true);
            $result = $this->chatService->chat();
            $chatDuration = microtime(true) - $chatStartTime;

            Log::info('[ChapterBuilderService::getChapterChat] Chat service returned', [
                'duration_seconds' => round($chatDuration, 2),
                'result_type' => gettype($result),
                'has_completion' => is_array($result) && isset($result['completion']),
                'completion_length' => is_array($result) && isset($result['completion']) ? strlen($result['completion']) : 0,
            ]);

            return $result;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::getChapterChat] Exception', [
                'book_id' => $book->id,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array  $data
     */
    public function getChapterTitle(string $bookId, string $chapterId, string $userId, string $chapterMessage)
    {
        Log::debug('[ChapterBuilderService::getChapterTitle] Starting', [
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'user_id' => $userId,
            'message_length' => strlen($chapterMessage),
        ]);

        try {
            $book = $this->bookService->getById($bookId, ['type']);
            $chapterLabel = $this->getChapterLabel($book->type);

            $this->setBackstory($bookId);
            $this->chatService->setResponseFormat('text');
            $this->chatService->addAssistantMessage('In the latest '.$chapterLabel.': '.$chapterMessage);
            $this->chatService->addUserMessage('In plain text, write a '.$chapterLabel.' title. Do not include the word "Scene", "Chapter" or the '.$chapterLabel.' number.');

            $titleresponse = $this->chatService->chat();
            $this->chatService->trackRequestLog($bookId, $chapterId, $userId, 'chapter_title', $titleresponse, $book->profile_id);

            Log::debug('[ChapterBuilderService::getChapterTitle] Completed', [
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'has_response' => ! empty($titleresponse),
            ]);

            return $titleresponse;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::getChapterTitle] Exception', [
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array  $data
     */
    public function getCta(string $bookId, string $chapterId, string $userId, string $chapterMessage)
    {
        Log::debug('[ChapterBuilderService::getCta] Starting', [
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'user_id' => $userId,
            'message_length' => strlen($chapterMessage),
        ]);

        try {
            $book = $this->bookService->getById($bookId, ['type']);
            $chapterLabel = $this->getChapterLabel($book->type);
            $isScript = $this->isScriptBasedType($book->type);

            $this->setBackstory($bookId);
            $this->chatService->addAssistantMessage('In this '.$chapterLabel.': '.$chapterMessage);

            $ctaPrompt = $isScript
                ? 'Write a single compelling question in less than 14 words that will entice the audience to want to see what happens next in this script.'
                : 'Write a single exciting question in less than 14 words that will entice the reader to want to read further.';

            $this->chatService->addUserMessage($ctaPrompt);

            $cta = $this->chatService->chat();
            $this->chatService->trackRequestLog($bookId, $chapterId, $userId, 'chapter_cta', $cta, $book->profile_id);

            Log::debug('[ChapterBuilderService::getCta] Completed', [
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'has_response' => ! empty($cta),
            ]);

            return $cta;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::getCta] Exception', [
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getSummary(string $bookId, string $chapterMessage, ?string $chapterId = null)
    {
        Log::debug('[ChapterBuilderService::getSummary] Starting', [
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'message_length' => strlen($chapterMessage),
        ]);

        try {
            $book = $this->bookService->getById($bookId, ['type', 'user_id', 'profile_id']);
            $chapterLabel = $this->getChapterLabel($book->type);

            $this->setBackstory($bookId);
            $this->chatService->addUserMessage('What is the '.$chapterLabel.' text?');
            $this->chatService->addAssistantMessage($chapterMessage);

            $this->chatService->addUserMessage('Summarize this text. Respond in plain text with the key events of only this '.$chapterLabel.'. Include character names, descriptions, age, gender, experiences, thoughts, goals and nationalities. Do not add commentary.');

            $result = $this->chatService->chat();

            $this->chatService->trackRequestLog(
                $bookId,
                $chapterId ?? '0',
                $book->user_id,
                'chapter_summary',
                $result,
                $book->profile_id
            );

            Log::debug('[ChapterBuilderService::getSummary] Completed', [
                'book_id' => $bookId,
                'has_result' => ! empty($result),
                'result_type' => gettype($result),
                'has_completion' => is_array($result) && isset($result['completion']),
            ]);

            return $result;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::getSummary] Exception', [
                'book_id' => $bookId,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getBookSummary(string $bookId, string $chapterId, string $userId, string $summary)
    {
        Log::debug('[ChapterBuilderService::getBookSummary] Starting', [
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'user_id' => $userId,
            'summary_length' => strlen($summary),
        ]);

        try {
            $book = $this->bookService->getById($bookId, ['id'], ['with' => ['chapters']]);

            $completedChaptersCount = $book->chapters->where('status', 'complete')->count();

            Log::debug('[ChapterBuilderService::getBookSummary] Chapters check', [
                'book_id' => $bookId,
                'completed_chapters_count' => $completedChaptersCount,
            ]);

            if ($completedChaptersCount == 0) {
                Log::debug('[ChapterBuilderService::getBookSummary] No completed chapters, returning null');

                return null;
            }

            foreach ($book->chapters->where('status', 'complete') as $chapter) {
                $this->chatService->addUserMessage('What happened in act/chapter '.$chapter->sort.'?');
                $this->chatService->addAssistantMessage($chapter->summary);
            }

            $this->chatService->addUserMessage('Provide a summary of this story so far. Respond with only the plain text summary.');

            $bookSummary = $this->chatService->chat();
            $this->chatService->trackRequestLog($bookId, $chapterId, $userId, 'book_summary', $bookSummary, $book->profile_id);

            Log::debug('[ChapterBuilderService::getBookSummary] Completed', [
                'book_id' => $bookId,
                'has_summary' => ! empty($bookSummary),
            ]);

            return $bookSummary;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::getBookSummary] Exception', [
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the appropriate label for a chapter/scene based on book type.
     */
    protected function getChapterLabel(?string $bookType): string
    {
        if ($bookType === 'theatre' || $bookType === 'screenplay') {
            return 'scene';
        }

        return 'chapter';
    }

    /**
     * Check if the book type uses script-based formatting (theatre/screenplay).
     */
    protected function isScriptBasedType(?string $bookType): bool
    {
        return in_array($bookType, ['theatre', 'screenplay']);
    }

    /**
     * Get formatting rules for script-based book types (theatre/screenplay).
     *
     * @return array<string>
     */
    protected function getScriptFormattingRules(string $bookType): array
    {
        $rules = [];

        if ($bookType === 'theatre') {
            $rules = [
                'Format as a professional theatre play script',
                'Start with a scene heading describing the location and time (e.g., "INT. LIVING ROOM - NIGHT")',
                'Include stage directions in parentheses or italicized format describing character movements, emotions, and scene setup',
                'Write dialogue with CHARACTER NAME in capitals followed by a colon, then their spoken line',
                'Add parenthetical notes before dialogue to indicate tone or action (e.g., "(nervously)" or "(crossing to the window)")',
                'Include blocking directions for character positions and movements on stage',
                'Describe any sound effects or lighting changes needed for the scene',
                'Each paragraph should either be a stage direction or a character\'s dialogue',
                'Keep dialogue natural and speakable - actors will perform these lines aloud',
            ];
        } elseif ($bookType === 'screenplay') {
            $rules = [
                'Format as a professional screenplay/film script',
                'Start with a scene heading (slugline) in ALL CAPS (e.g., "INT. COFFEE SHOP - DAY" or "EXT. FOREST - NIGHT")',
                'Write action lines in present tense describing what the camera sees',
                'Format character names in ALL CAPS when they first appear, and before each dialogue block',
                'Include parentheticals for specific delivery instructions (e.g., "(whispered)" or "(O.S.)" for off-screen)',
                'Add brief visual descriptions of settings, characters\' appearances, and important props',
                'Use transitions sparingly (CUT TO:, FADE OUT:) only when dramatically necessary',
                'Write dialogue that sounds natural when spoken - avoid overly literary language',
                'Include character reactions and expressions as action lines between dialogue',
                'Keep action descriptions concise and visual - show, don\'t tell',
            ];
        }

        return $rules;
    }

    /**
     * Generate an enticing prompt suggestion based on the last chapter's cliffhanger.
     */
    public function generatePromptSuggestion(Book $book, \App\Models\Chapter $lastChapter): ?string
    {
        try {
            $chatService = app(\App\Services\Ai\AiManager::class)->chat();
            $chatService->resetMessages();
            $chatService->setMaxTokens(100);
            $chatService->setTemperature(0.8);

            $chapterLabel = $this->getChapterLabel($book->type);
            $isScript = $this->isScriptBasedType($book->type);

            // Get the last ~500 characters of the chapter body for context
            $body = $lastChapter->body ?? '';
            $contextLength = min(strlen($body), 800);
            $lastPortion = substr($body, -$contextLength);

            $systemPrompt = <<<PROMPT
You are a creative writing assistant helping a reader continue their story. 
Based on the ending of the last {$chapterLabel}, generate ONE short, enticing sentence fragment (15-30 words max) that:
- References a specific moment, character, or situation from the text
- Trails off with "..." to invite the reader to complete the thought
- Creates curiosity about what happens next
- Feels like a natural continuation of the story's voice

Do NOT include any preamble, quotation marks, or explanation. Just output the fragment directly.
PROMPT;

            $contentType = $isScript ? 'scene' : 'chapter';
            $userPrompt = <<<PROMPT
Here's how the last {$contentType} ended:

"{$lastPortion}"

Generate an enticing prompt fragment that teases what could happen next:
PROMPT;

            $chatService->setContext($systemPrompt);
            $chatService->addUserMessage($userPrompt);

            $result = $chatService->chat();

            $chatService->trackRequestLog(
                $book->id,
                $lastChapter->id,
                $book->user_id,
                'prompt_suggestion',
                $result,
                $book->profile_id
            );

            if (! empty($result['error'])) {
                Log::warning('[ChapterBuilderService::generatePromptSuggestion] AI error', [
                    'book_id' => $book->id,
                    'error' => $result['error'],
                ]);

                return null;
            }

            $suggestion = trim($result['completion'] ?? '');

            // Clean up the suggestion - remove quotes if present
            $suggestion = trim($suggestion, "\"'\u{201C}\u{201D}\u{2018}\u{2019}");

            // Make sure it ends with "..." for that trailing effect
            if (! str_ends_with($suggestion, '...')) {
                $suggestion = rtrim($suggestion, '.!?,;:').'...';
            }

            return $suggestion ?: null;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::generatePromptSuggestion] Exception', [
                'book_id' => $book->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Rebuild an existing chapter completely with new content.
     *
     * @param  \App\Models\Chapter  $chapter  The chapter to rebuild
     * @param  array{final_chapter?: bool, user_prompt?: string|null}  $data  The chapter data
     */
    public function rebuildChapter(\App\Models\Chapter $chapter, array $data): \App\Models\Chapter
    {
        $startTime = microtime(true);

        Log::info('[ChapterBuilderService::rebuildChapter] Starting chapter rebuild', [
            'chapter_id' => $chapter->id,
            'book_id' => $chapter->book_id,
            'has_user_prompt' => ! empty($data['user_prompt'] ?? null),
            'is_final_chapter' => $data['final_chapter'] ?? false,
        ]);

        try {
            $book = $this->bookService->getById($chapter->book_id, null, ['with' => ['chapters', 'user']]);

            if (! $book) {
                throw new \RuntimeException("Book not found: {$chapter->book_id}");
            }

            $user = \Illuminate\Support\Facades\Auth::user();
            $profileId = session('current_profile_id');

            // Fall back to book's user when running in a job context
            if (! $user && $book->user_id) {
                $user = $book->user;
                $profileId = $profileId ?? $book->profile_id;
            }

            if (! $user) {
                throw new \RuntimeException('No authenticated user found when rebuilding chapter');
            }

            Log::debug('[ChapterBuilderService::rebuildChapter] Calling getCombinedChapterData');
            $combinedStartTime = microtime(true);
            $chapterContent = $this->getCombinedChapterData($chapter->book_id, $data, $chapter->id);
            $combinedDuration = microtime(true) - $combinedStartTime;

            if (empty($chapterContent['body'])) {
                throw new \RuntimeException('Empty chapter body returned from AI');
            }

            $updateData = [
                'body' => $chapterContent['body'],
                'summary' => $chapterContent['summary'] ?? '',
                'title' => $chapterContent['title'] ?? null,
                'status' => 'complete',
                'user_prompt' => $data['user_prompt'] ?? null,
                'final_chapter' => $data['final_chapter'] ?? false,
            ];

            $sceneImages = $chapterContent['scene_images'] ?? [];
            $imagePrompt = $chapterContent['image_prompt'] ?? null;

            // Create header image record BEFORE updating chapter so the event listener can dispatch the job
            $headerImage = null;
            if (! empty($imagePrompt)) {
                $imageService = app(\App\Services\Image\ImageService::class);
                $headerImage = $imageService->createChapterHeaderImage($chapter, $imagePrompt);
                $updateData['header_image_id'] = $headerImage->id;

                Log::info('[ChapterBuilderService::rebuildChapter] Created header image record', [
                    'chapter_id' => $chapter->id,
                    'image_id' => $headerImage->id,
                    'prompt_preview' => substr($imagePrompt, 0, 100),
                ]);
            }

            Log::info('[ChapterBuilderService::rebuildChapter] Updating chapter with new content', [
                'chapter_id' => $chapter->id,
                'body_length' => strlen($updateData['body']),
                'has_header_image_id' => ! empty($updateData['header_image_id']),
                'scene_images_count' => count($sceneImages),
            ]);

            // This update triggers ChapterUpdatedEvent which fires CreateChapterImageListener
            // The listener will dispatch CreateChapterImageJob since header_image_id is now set
            $chapter = $this->chapterService->updateById($chapter->id, $updateData);

            // Dispatch inline images generation as a background job
            if (! empty($sceneImages) && is_array($sceneImages)) {
                Log::info('[ChapterBuilderService::rebuildChapter] Dispatching inline images job', [
                    'chapter_id' => $chapter->id,
                    'scene_count' => count($sceneImages),
                ]);

                CreateChapterInlineImagesJob::dispatch($chapter->fresh(), $sceneImages)->onQueue('images');
            }

            $endTime = microtime(true);
            $totalDuration = $endTime - $startTime;

            Log::info('[ChapterBuilderService::rebuildChapter] Chapter rebuild completed', [
                'chapter_id' => $chapter->id,
                'total_duration_seconds' => round($totalDuration, 2),
                'combined_generation_seconds' => round($combinedDuration, 2),
            ]);

            return $chapter;
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::rebuildChapter] Exception thrown', [
                'chapter_id' => $chapter->id,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Edit an existing chapter's content based on user instructions.
     *
     * @param  \App\Models\Chapter  $chapter  The chapter to edit
     * @param  string  $instructions  User's edit instructions
     * @return array{body: string, summary: string, scene_images: array<array{paragraph_index: int, prompt: string}>}
     */
    public function editChapterContent(\App\Models\Chapter $chapter, string $instructions): array
    {
        Log::info('[ChapterBuilderService::editChapterContent] Starting edit', [
            'chapter_id' => $chapter->id,
            'book_id' => $chapter->book_id,
            'instructions_length' => strlen($instructions),
        ]);

        try {
            $book = $this->bookService->getById($chapter->book_id, null, ['with' => ['characters']]);

            if ($book->type == 'theatre') {
                $chapterLabel = 'scene';
                $bookTypeLabel = 'theatre play script';
            } elseif ($book->type == 'screenplay') {
                $chapterLabel = 'scene';
                $bookTypeLabel = 'screenplay';
            } else {
                $chapterLabel = 'chapter';
                $bookTypeLabel = 'chapter book';
            }

            $isScriptFormat = in_array($book->type, ['theatre', 'screenplay']);

            $targetWordCount = $this->getBodyWordCount($book->id);
            $minimumWordCount = (int) round($targetWordCount * 0.75);

            $systemPrompt = [
                'you_are' => 'An expert editor modifying a '.$chapterLabel.' in a '.$book->genre.' '.$bookTypeLabel.' for '.$book->age_level.' year old readers.',
                'story_details' => [
                    'format' => $bookTypeLabel,
                    'genre' => $book->genre,
                    'title' => $book->title,
                ],
                'rules' => [
                    'Maintain the overall narrative and plot points unless specifically asked to change them.',
                    'Keep the same characters unless asked to add or remove them.',
                    'Preserve the writing style and tone of the original.',
                    'The '.$chapterLabel.' body MUST be between '.$minimumWordCount.' and '.$targetWordCount.' words. Aim for '.$targetWordCount.' words.',
                    'No '.$chapterLabel.' number or title in the body.',
                    'The body text MUST use paragraph breaks. Separate each paragraph with \\n\\n (two newlines).',
                ],
            ];

            if ($isScriptFormat) {
                $scriptRules = $this->getScriptFormattingRules($book->type);
                $systemPrompt['rules'] = array_merge($systemPrompt['rules'], $scriptRules);
            }

            // Add character descriptions for scene image generation
            $characters = $book->characters()->get();
            if ($characters->count() > 0) {
                $characterDescriptions = [];
                foreach ($characters as $character) {
                    $desc = $character->name;
                    if ($character->age) {
                        $desc .= " ({$character->age} years old)";
                    }
                    if ($character->gender) {
                        $desc .= ", {$character->gender}";
                    }
                    if ($character->description) {
                        $desc .= ": {$character->description}";
                    }
                    $characterDescriptions[] = $desc;
                }
                $systemPrompt['story_details']['characters'] = $characterDescriptions;
            }

            $characterInstructions = $this->getCharacterIdentificationInstructions($characters);
            $physicalPresenceRule = 'CRITICAL: ONLY include characters who are PHYSICALLY PRESENT at the scene location - do NOT include characters who are merely mentioned, remembered, or talked about but not bodily there. ';

            $imageFormatHint = $this->isFlux2Model() ? '16:9 landscape format.' : '9:16 portrait format.';

            $this->chatService->resetMessages();
            $this->chatService->setResponseFormat('json_object');
            $this->chatService->setMaxTokens(8000);
            $this->chatService->addSystemMessage(json_encode($systemPrompt));

            $outputFormat = [
                'body' => 'The complete edited '.$chapterLabel.' text',
                'summary' => 'A detailed summary with key events, character names, descriptions, ages, genders, experiences, thoughts, goals, and nationalities. No commentary.',
                'scene_images' => [
                    [
                        'paragraph_index' => 'The 0-based paragraph index where this scene occurs (early in the '.$chapterLabel.', around 20-30% through)',
                        'prompt' => 'A detailed visual prompt for an image generation AI describing this specific scene. '.$physicalPresenceRule.$characterInstructions.' Include setting details, lighting, mood, and action. '.$imageFormatHint,
                    ],
                    [
                        'paragraph_index' => 'The 0-based paragraph index where this scene occurs (later in the '.$chapterLabel.', around 60-80% through)',
                        'prompt' => 'A detailed visual prompt for an image generation AI describing this specific scene. '.$physicalPresenceRule.$characterInstructions.' Include setting details, lighting, mood, and action. '.$imageFormatHint,
                    ],
                ],
            ];

            $userPrompt = "Here is the current {$chapterLabel} content:\n\n";
            $userPrompt .= $chapter->body."\n\n";
            $userPrompt .= "Please edit this {$chapterLabel} with the following changes: {$instructions}\n\n";
            $userPrompt .= 'Respond with a JSON object containing: '.json_encode($outputFormat);

            $this->chatService->addUserMessage($userPrompt);

            Log::info('[ChapterBuilderService::editChapterContent] Calling chat service');
            $chatStartTime = microtime(true);
            $result = $this->chatService->chat();
            $chatDuration = microtime(true) - $chatStartTime;

            Log::info('[ChapterBuilderService::editChapterContent] Chat service returned', [
                'duration_seconds' => round($chatDuration, 2),
            ]);

            $this->chatService->trackRequestLog(
                $chapter->book_id,
                $chapter->id,
                $book->user_id,
                'chapter_edit',
                $result,
                $book->profile_id
            );

            if (empty($result['completion'])) {
                Log::error('[ChapterBuilderService::editChapterContent] Empty completion');
                throw new \RuntimeException('Empty response from AI service');
            }

            $jsonData = $this->parseJsonResponse($result['completion']);

            if ($jsonData === null) {
                Log::error('[ChapterBuilderService::editChapterContent] JSON decode error', [
                    'error' => json_last_error_msg(),
                ]);
                throw new \RuntimeException('Failed to decode JSON response: '.json_last_error_msg());
            }

            Log::info('[ChapterBuilderService::editChapterContent] Edit completed successfully', [
                'chapter_id' => $chapter->id,
                'body_length' => strlen($jsonData['body'] ?? ''),
                'scene_images_count' => count($jsonData['scene_images'] ?? []),
            ]);

            $body = $this->stripQuotes($jsonData['body'] ?? '') ?? '';
            $body = $this->normalizeBodyParagraphs($body);

            return [
                'body' => $body,
                'summary' => $this->stripQuotes($jsonData['summary'] ?? ''),
                'scene_images' => $jsonData['scene_images'] ?? [],
            ];
        } catch (Throwable $e) {
            Log::error('[ChapterBuilderService::editChapterContent] Exception', [
                'chapter_id' => $chapter->id,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
