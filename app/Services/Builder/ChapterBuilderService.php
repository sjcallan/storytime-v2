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
            $chapterContent = $this->getCombinedChapterData($bookId, $data);
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
            $data['image_prompt'] = $chapterContent['image_prompt'] ?? null;
            $data['status'] = 'complete';

            $sceneImages = $chapterContent['scene_images'] ?? [];

            // Store pending placeholders for inline images so frontend can show loading state
            if (! empty($sceneImages) && is_array($sceneImages)) {
                $pendingImages = [];
                foreach ($sceneImages as $index => $scene) {
                    $paragraphIndex = is_numeric($scene['paragraph_index'] ?? null)
                        ? (int) $scene['paragraph_index']
                        : $index * 2;
                    $pendingImages[] = [
                        'paragraph_index' => $paragraphIndex,
                        'url' => null,
                        'prompt' => $scene['prompt'] ?? '',
                        'status' => 'pending',
                    ];
                }
                $data['inline_images'] = $pendingImages;
            }

            Log::info('[ChapterBuilderService::buildChapter] Updating chapter with final data', [
                'chapter_id' => $chapter->id,
                'status' => 'complete',
                'summary_length' => strlen($data['summary']),
                'body_length' => strlen($data['body']),
                'has_title' => ! empty($data['title']),
                'has_image_prompt' => ! empty($data['image_prompt']),
                'scene_images_count' => count($sceneImages),
                'pending_inline_images' => count($data['inline_images'] ?? []),
            ]);

            $chapter = $this->chapterService->updateById($chapter->id, $data);

            // Dispatch inline images generation as a background job after chapter is saved
            if (! empty($sceneImages) && is_array($sceneImages)) {
                Log::info('[ChapterBuilderService::buildChapter] Dispatching inline images job', [
                    'chapter_id' => $chapter->id,
                    'scene_count' => count($sceneImages),
                ]);

                CreateChapterInlineImagesJob::dispatch($chapter, $sceneImages)->onQueue('images');
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

    public function getCombinedChapterData(string $bookId, array $data): array
    {
        Log::debug('[ChapterBuilderService::getCombinedChapterData] Starting', [
            'book_id' => $bookId,
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
                $userAddedPrompt = 'Ensure that it includes: '.$userPromptText;
            }

            $systemPrompt = [
                'you_are' => 'An author writing the next '.$chapterLabel.' in a '.$book->genre.' '.$bookTypeLabel.' for '.$book->age_level.' year old readers.',
                'story_details' => [
                    'format' => $bookTypeLabel,
                    'genre' => $book->genre,
                    'plot' => $book->plot,
                ],
                'rules' => [
                    'ensure narrative flow',
                    'The response must be '.$this->getBodyWordCount($book->id).' words or less',
                    'No '.$chapterLabel.' number or title in the body.',
                    'The characters must not be in more than 1 physical location.',
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

                $systemPrompt['rules'][] = 'End the '.$chapterLabel.' with a question that makes the reader want to find out what happens next.';
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
                    $systemPrompt['rules'][] = 'End the '.$chapterLabel.' with a cliffhanger that makes the reader want to find out what happens next.';
                }
            }

            $this->chatService->resetMessages();
            $this->chatService->setResponseFormat('json_object');
            $this->chatService->setMaxTokens(8000);
            $this->chatService->addSystemMessage(json_encode($systemPrompt));

            $characterInstructions = $this->getCharacterIdentificationInstructions($characters);

            $outputFormat = [
                'body' => 'The full '.$chapterLabel.' text, but no more than ',
                'title' => 'A compelling '.$chapterLabel.' title (plain text, no '.$chapterLabel.' number)',
                'summary' => 'A detailed summary with key events, character names, descriptions, ages, genders, experiences, thoughts, goals, and nationalities. No commentary.',
                'image_prompt' => 'A detailed one-sentence prompt for an image generation service describing a key scene from this '.$chapterLabel.'. '.$characterInstructions.' Describe the visual scene, setting, mood, and action.',
                'scene_images' => [
                    [
                        'paragraph_index' => 'The 0-based paragraph index where this scene occurs (early in the '.$chapterLabel.', around 20-30% through)',
                        'prompt' => 'A detailed visual prompt for an image generation AI describing this specific scene. '.$characterInstructions.' Include setting details, lighting, mood, and action. 16:9 landscape format.',
                    ],
                    [
                        'paragraph_index' => 'The 0-based paragraph index where this scene occurs (later in the '.$chapterLabel.', around 60-80% through)',
                        'prompt' => 'A detailed visual prompt for an image generation AI describing this specific scene. '.$characterInstructions.' Include setting details, lighting, mood, and action. 16:9 landscape format.',
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

            return [
                'body' => $this->stripQuotes($jsonData['body'] ?? ''),
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
        $style = $this->getSceneImageStylePrefix($book);

        $characterImages = [];

        // Add book cover image first
        if ($book->cover_image) {
            $coverUrl = $book->cover_image;
            if (! str_starts_with($coverUrl, 'http')) {
                $coverUrl = $this->getCloudFrontImageUrl($coverUrl);
            }
            if ($coverUrl) {
                $characterImages[] = $coverUrl;
                Log::debug('[ChapterBuilderService::generateChapterImages] Added book cover image', [
                    'cover_url' => $coverUrl,
                ]);
            }
        }

        // Add last inline chapter image
        $lastInlineImage = null;
        foreach ($book->chapters->sortByDesc('sort') as $chapter) {
            if ($chapter->inline_images && is_array($chapter->inline_images)) {
                foreach (array_reverse($chapter->inline_images) as $image) {
                    if (! empty($image['url']) && isset($image['status']) && $image['status'] !== 'pending') {
                        $lastInlineImage = $image['url'];
                        break 2;
                    }
                }
            }
        }

        if ($lastInlineImage) {
            if (! str_starts_with($lastInlineImage, 'http')) {
                $lastInlineImage = $this->getCloudFrontImageUrl($lastInlineImage);
            }
            if ($lastInlineImage) {
                $characterImages[] = $lastInlineImage;
                Log::debug('[ChapterBuilderService::generateChapterImages] Added last inline chapter image', [
                    'image_url' => $lastInlineImage,
                ]);
            }
        }

        // Add character portraits
        if ($book->characters) {
            foreach ($book->characters as $character) {
                if ($character->portrait_image) {
                    $portraitUrl = $character->portrait_image;
                    if (! str_starts_with($portraitUrl, 'http')) {
                        $portraitUrl = $this->getCloudFrontImageUrl($portraitUrl);
                    }
                    if ($portraitUrl) {
                        $characterImages[] = $portraitUrl;
                        Log::debug('[ChapterBuilderService::generateChapterImages] Added character portrait', [
                            'character_id' => $character->id,
                            'character_name' => $character->name,
                            'portrait_url' => $portraitUrl,
                        ]);
                    }
                }
            }
        }

        Log::info('[ChapterBuilderService::generateChapterImages] Reference images collected', [
            'total_reference_images' => count($characterImages),
            'reference_image_urls' => $characterImages,
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

            $fullPrompt = trim($style.' '.$this->stripQuotes($scene['prompt']));

            Log::debug('[ChapterBuilderService::generateChapterImages] Generating image', [
                'scene_index' => $index,
                'paragraph_index' => $paragraphIndex,
                'prompt_preview' => substr($fullPrompt, 0, 100),
                'character_images_count' => count($characterImages),
                'character_image_urls' => $characterImages,
            ]);

            try {
                $imageResponse = $this->replicateApiService->generateImage(
                    $fullPrompt,
                    $characterImages,
                    '16:9'
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
                        'prompt' => $scene['prompt'],
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
     * Get style prefix for scene images based on book genre and age level.
     * Similar to BookCoverService::getStylePrefix() but optimized for scene images.
     */
    protected function getSceneImageStylePrefix(Book $book): string
    {
        // CRITICAL: No text, letters, words, or writing on the image
        $style = '';

        // Age-based style: cartoon for kids/pre-teens, realistic for teens/adults
        if ($book->age_level <= 13) {
            $style .= 'Graphic cartoon illustration in the style of Neil Gaiman and Dave McKean, ';
            $style .= 'whimsical yet slightly dark, hand-drawn aesthetic, rich textures, ';
            $style .= 'imaginative and dreamlike, vibrant colors with moody undertones, ';
        } else {
            $style .= 'Realsitic photograph, professional quality, dramatic composition, ';
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
        $instructions = 'NO character names in the prompt. ';
        $instructions .= 'Identify each character as "[Gender] [Number]" (e.g., "Male 1", "Female 2"). ';
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

            $userPrompt = '';
            $systemPrompt = [
                'you_are' => 'An author writing the next '.$chapterLabel.' in a '.$book->genre.' '.$bookTypeLabel.' for '.$book->age_level.' year old readers.',
                'story_details' => [
                    'format' => $bookTypeLabel,
                    'genre' => $book->genre,
                    'plot' => $book->plot,
                ],
                'rules' => [
                    'ensure narrative flow',
                    'The response must be '.$this->getBodyWordCount($book->id).' words or less',
                    'No '.$chapterLabel.' number or title. No '.$chapterLabel.' summary at the end.',
                    'The characters must not be in more than 1 physical location.',
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
            $this->chatService->trackRequestLog($bookId, $chapterId, $userId, 'chapter_title', $titleresponse);

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
            $this->chatService->trackRequestLog($bookId, $chapterId, $userId, 'chapter_cta', $cta);

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

    public function getSummary(string $bookId, string $chapterMessage)
    {
        Log::debug('[ChapterBuilderService::getSummary] Starting', [
            'book_id' => $bookId,
            'message_length' => strlen($chapterMessage),
        ]);

        try {
            $book = $this->bookService->getById($bookId, ['type']);
            $chapterLabel = $this->getChapterLabel($book->type);

            $this->setBackstory($bookId);
            $this->chatService->addUserMessage('What is the '.$chapterLabel.' text?');
            $this->chatService->addAssistantMessage($chapterMessage);

            $this->chatService->addUserMessage('Summarize this text. Respond in plain text with the key events of only this '.$chapterLabel.'. Include character names, descriptions, age, gender, experiences, thoughts, goals and nationalities. Do not add commentary.');

            $result = $this->chatService->chat();

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
            $this->chatService->trackRequestLog($bookId, $chapterId, $userId, 'book_summary', $bookSummary);

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
}
