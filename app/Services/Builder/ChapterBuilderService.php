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

            Log::debug('[ChapterBuilderService::buildChapter] Calling getNextChapterResponse');
            $bodyStartTime = microtime(true);
            $body = $this->getNextChapterResponse($bookId, $data);
            $bodyDuration = microtime(true) - $bodyStartTime;

            Log::info('[ChapterBuilderService::buildChapter] Chapter body generated', [
                'chapter_id' => $chapter->id,
                'body_type' => gettype($body),
                'body_is_array' => is_array($body),
                'has_completion' => is_array($body) && isset($body['completion']),
                'body_length' => is_string($body) ? strlen($body) : (is_array($body) && isset($body['completion']) ? strlen($body['completion']) : 0),
                'duration_seconds' => round($bodyDuration, 2),
            ]);

            $data['body'] = $body;

            $completionText = is_array($body) && isset($body['completion']) ? $body['completion'] : $body;

            if (empty($completionText)) {
                Log::error('[ChapterBuilderService::buildChapter] Empty body/completion returned', [
                    'chapter_id' => $chapter->id,
                    'body_value' => $body,
                ]);
                throw new \RuntimeException('Empty chapter body returned from AI');
            }

            Log::debug('[ChapterBuilderService::buildChapter] Generating chapter summary');
            $summaryStartTime = microtime(true);
            $summary = $this->getSummary($bookId, $completionText);
            $summaryDuration = microtime(true) - $summaryStartTime;

            Log::debug('[ChapterBuilderService::buildChapter] Summary generated', [
                'chapter_id' => $chapter->id,
                'summary_type' => gettype($summary),
                'has_completion' => is_array($summary) && isset($summary['completion']),
                'duration_seconds' => round($summaryDuration, 2),
            ]);

            $summaryText = $this->stripQuotes($summary['completion'] ?? '');

            // Log::debug('[ChapterBuilderService::buildChapter] Tracking summary request log');
            // $this->chatService->trackRequestLog($bookId, $chapter->id, $user->id, 'chapter_summary', $summary);
            $data['summary'] = $summaryText;

            $data['status'] = 'complete';

            Log::info('[ChapterBuilderService::buildChapter] Updating chapter with final data', [
                'chapter_id' => $chapter->id,
                'status' => 'complete',
                'summary_length' => strlen($summaryText),
                'body_length' => is_string($data['body']) ? strlen($data['body']) : 'array',
            ]);

            $chapter = $this->chapterService->updateById($chapter->id, $data);

            $endTime = microtime(true);
            $totalDuration = $endTime - $startTime;

            Log::info('[ChapterBuilderService::buildChapter] Chapter build completed successfully', [
                'book_id' => $bookId,
                'chapter_id' => $chapter->id,
                'total_duration_seconds' => round($totalDuration, 2),
                'body_generation_seconds' => round($bodyDuration, 2),
                'summary_generation_seconds' => round($summaryDuration, 2),
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

                $systemPrompt['rules'][] = 'End the '.$chapterLabel.' with a cliffhanger that makes the reader want to find out what happens next.';

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
