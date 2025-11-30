<?php

namespace App\Services\Builder;

use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChapterBuilderService extends BuilderService
{
    public function buildChapter(string $bookId, array $data)
    {
        $startTime = microtime(true);
        $book = $this->bookService->getById($bookId, null, ['with' => ['chapters']]);

        $chapter = $this->chapterService->store([
            'book_id' => $book->id,
            'user_id' => Auth::user()->id,
            'profile_id' => session('current_profile_id'),
            'sort' => $book->chapters->where('status', 'complete')->count() + 1,
        ], ['events' => false]);

        $body = $this->getNextChapterResponse($bookId, $data);
        $data['body'] = $body;

        $summary = $this->getSummary($bookId, $body['completion']);
        $summaryText = $this->stripQuotes($summary['completion']);

        $this->chatService->trackRequestLog($bookId, $chapter->id, Auth::user()->id, 'chapter_summary', $summary);
        $data['summary'] = $summaryText;

        $data['status'] = 'complete';

        $chapter = $this->chapterService->updateById($chapter->id, $data);

        $endTime = microtime(true);
        Log::debug('Build time: '.$endTime - $startTime);

        return $chapter;
    }

    public function getNextChapterResponse(string $bookId, array $data)
    {
        Log::debug('BODY');

        $book = $this->bookService->getById($bookId, null, ['with' => ['chapters']]);

        if ($book->type == 'story') {
            $this->setBackstory($bookId);

            return $this->getStorybookChat();
        }

        $body = $this->getChapterChat($book, $data);

        return $this->stripQuotes($body['completion']);
    }

    protected function getStorybookChat()
    {
        $this->chatService->addUserMessage('Write this book.');

        return $this->chatService->chat();
    }

    protected function getChapterChat(Book $book, ?array $data = null)
    {
        $finalChapter = $data['final_chapter'];

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

        if ($userAddedPrompt = $data['user_prompt']) {
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

        if ($chapters->count() == 0) {
            $userPrompt = 'Write the first '.$chapterLabel.' of this story.';

            if (array_key_exists('first_chapter_prompt', $data) && $data['first_chapter_prompt'] != '') {
                $userPrompt .= ' about: '.$data['first_chapter_prompt'];
            }

            $systemPrompt['rules'][] = 'End the '.$chapterLabel.' with a cliffhanger that makes the reader want to find out what happens next.';
        } else {
            $previousChapter = $chapters->where('status', 'complete')->last();

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

        Log::debug($systemPrompt, ['ChapterBuilderService']);

        $this->chatService->resetMessages();
        $this->chatService->addSystemMessage(json_encode($systemPrompt));
        $this->chatService->addUserMessage($userPrompt.' '.$userAddedPrompt);

        $this->chatService->addUserMessage($userPrompt);

        return $this->chatService->chat();
    }

    /**
     * @param  array  $data
     */
    public function getChapterTitle(string $bookId, string $chapterId, int $userId, string $chapterMessage)
    {
        Log::debug('CHAPTER TITLE');
        $this->setBackstory($bookId);
        $this->chatService->setResponseFormat('text');
        $this->chatService->addAssistantMessage('In the latest act/chapter: '.$chapterMessage);
        $this->chatService->addUserMessage('In plain text, write a act/chapter title for this latest scene. Do not include the word "Scene", "Chapter" or the chapter number.');

        $titleresponse = $this->chatService->chat();
        $this->chatService->trackRequestLog($bookId, $chapterId, $userId, 'chapter_title', $titleresponse);

        return $titleresponse;
    }

    /**
     * @param  array  $data
     */
    public function getCta(string $bookId, string $chapterId, int $userId, string $chapterMessage)
    {
        Log::debug('CHAPTER CTA');
        $this->setBackstory($bookId);
        $this->chatService->addAssistantMessage('In this act/chapter: '.$chapterMessage);
        $this->chatService->addUserMessage('Write a single exciting question in less than 14 words that will entice the reader to want to read further.');

        $cta = $this->chatService->chat();
        $this->chatService->trackRequestLog($bookId, $chapterId, $userId, 'chapter_cta', $cta);

        return $cta;
    }

    public function getSummary(string $bookId, string $chapterMessage)
    {
        Log::debug('CHAPTER SUMMARY');

        $this->setBackstory($bookId);
        $this->chatService->addUserMessage('What is the act/chapter text?');
        $this->chatService->addAssistantMessage($chapterMessage);

        $this->chatService->addUserMessage('Summarize this text. Respond in plain text with the key events of only this act/chapter. Include character names, descriptions, age, gender, experiences, thoughts, goals and nationalities. Do not add commentary.');

        return $this->chatService->chat();
    }

    public function getBookSummary(string $bookId, string $chapterId, int $userId, string $summary)
    {
        Log::debug('BOOK SUMMARY');
        $book = $this->bookService->getById($bookId, ['id'], ['with' => ['chapters']]);

        if ($book->chapters->where('status', 'complete')->count() == 0) {
            return null;
        }

        foreach ($book->chapters->where('status', 'complete') as $chapter) {
            $this->chatService->addUserMessage('What happened in act/chapter '.$chapter->sort.'?');
            $this->chatService->addAssistantMessage($chapter->summary);
        }

        $this->chatService->addUserMessage('Provide a summary of this story so far. Respond with only the plain text summary.');

        $bookSummary = $this->chatService->chat();
        $this->chatService->trackRequestLog($bookId, $chapterId, $userId, 'book_summary', $bookSummary);

        return $bookSummary;
    }
}
