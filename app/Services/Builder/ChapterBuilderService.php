<?php

namespace App\Services\Builder;

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

            $inlineImages = [];
            $sceneImages = $chapterContent['scene_images'] ?? [];

            if (! empty($sceneImages) && is_array($sceneImages)) {
                Log::info('[ChapterBuilderService::buildChapter] Generating inline images', [
                    'chapter_id' => $chapter->id,
                    'scene_count' => count($sceneImages),
                ]);

                $imageGenStartTime = microtime(true);
                $inlineImages = $this->generateChapterImages($bookId, $chapter->id, $sceneImages);
                $imageGenDuration = microtime(true) - $imageGenStartTime;

                Log::info('[ChapterBuilderService::buildChapter] Inline images generated', [
                    'chapter_id' => $chapter->id,
                    'images_count' => count($inlineImages),
                    'duration_seconds' => round($imageGenDuration, 2),
                ]);
            }

            $data['inline_images'] = ! empty($inlineImages) ? $inlineImages : null;

            Log::info('[ChapterBuilderService::buildChapter] Updating chapter with final data', [
                'chapter_id' => $chapter->id,
                'status' => 'complete',
                'summary_length' => strlen($data['summary']),
                'body_length' => strlen($data['body']),
                'has_title' => ! empty($data['title']),
                'has_image_prompt' => ! empty($data['image_prompt']),
                'inline_images_count' => count($inlineImages),
            ]);

            $chapter = $this->chapterService->updateById($chapter->id, $data);

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
                $chapterLabel = 'act';
                $bookTypeLabel = 'theatre play script';
            } elseif ($book->type == 'screenplay') {
                $chapterLabel = 'scene';
                $bookTypeLabel = 'screenplay';
            } else {
                $chapterLabel = 'chapter';
                $bookTypeLabel = 'chapter book';
            }

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
            $this->chatService->addSystemMessage(json_encode($systemPrompt));

            $characterInstructions = '';
            if ($characters->count() > 0) {
                $characterInstructions = ' When describing characters in scene prompts, use the physical descriptions from the characters list provided in story_details. Match characters by their roles and descriptions, not names.';
            }

            $outputFormat = [
                'body' => 'The full '.$chapterLabel.' text',
                'title' => 'A compelling '.$chapterLabel.' title (plain text, no '.$chapterLabel.' number)',
                'summary' => 'A detailed summary with key events, character names, descriptions, ages, genders, experiences, thoughts, goals, and nationalities. No commentary.',
                'image_prompt' => 'A detailed one-sentence prompt for an image generation service describing a key scene from this '.$chapterLabel.'. Exclude character names. Describe the visual scene, setting, mood, and action.',
                'scene_images' => [
                    [
                        'paragraph_index' => 'The 0-based paragraph index where this scene occurs (early in the '.$chapterLabel.', around 20-30% through)',
                        'prompt' => 'A detailed visual prompt for an image generation AI describing this specific scene. Include character physical descriptions (age, gender, clothing, hair, features) matching the characters from story_details, setting details, lighting, mood, and action. NO character names.'.$characterInstructions.' 16:9 landscape format.',
                    ],
                    [
                        'paragraph_index' => 'The 0-based paragraph index where this scene occurs (later in the '.$chapterLabel.', around 60-80% through)',
                        'prompt' => 'A detailed visual prompt for an image generation AI describing this specific scene. Include character physical descriptions (age, gender, clothing, hair, features) matching the characters from story_details, setting details, lighting, mood, and action. NO character names.'.$characterInstructions.' 16:9 landscape format.',
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

        $book = $this->bookService->getById($bookId, null, ['with' => ['characters']]);
        $style = $this->getSceneImageStylePrefix($book);

        $characterImages = [];
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

        Log::info('[ChapterBuilderService::generateChapterImages] Character portraits collected', [
            'character_images_count' => count($characterImages),
            'character_image_urls' => $characterImages,
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
                $chapterLabel = 'act';
                $bookTypeLabel = 'theatre play script';
            } elseif ($book->type == 'screenplay') {
                $chapterLabel = 'scene';
                $bookTypeLabel = 'screenplay';
            } else {
                $chapterLabel = 'chapter';
                $bookTypeLabel = 'chapter book';
            }

            Log::debug('[ChapterBuilderService::getChapterChat] Labels determined', [
                'chapter_label' => $chapterLabel,
                'book_type_label' => $bookTypeLabel,
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
            $this->setBackstory($bookId);
            $this->chatService->setResponseFormat('text');
            $this->chatService->addAssistantMessage('In the latest act/chapter: '.$chapterMessage);
            $this->chatService->addUserMessage('In plain text, write a act/chapter title for this latest scene. Do not include the word "Scene", "Chapter" or the chapter number.');

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
            $this->setBackstory($bookId);
            $this->chatService->addAssistantMessage('In this act/chapter: '.$chapterMessage);
            $this->chatService->addUserMessage('Write a single exciting question in less than 14 words that will entice the reader to want to read further.');

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
            $this->setBackstory($bookId);
            $this->chatService->addUserMessage('What is the act/chapter text?');
            $this->chatService->addAssistantMessage($chapterMessage);

            $this->chatService->addUserMessage('Summarize this text. Respond in plain text with the key events of only this act/chapter. Include character names, descriptions, age, gender, experiences, thoughts, goals and nationalities. Do not add commentary.');

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
}
