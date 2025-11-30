<?php

namespace App\Jobs\Chapter;

use App\Services\Book\BookService;
use App\Services\Builder\ChapterBuilderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateFirstChapterJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected string $bookId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ChapterBuilderService $chapterBuilderService, BookService $bookService): void
    {
        $book = $bookService->getById($this->bookId);

        $chapterBuilderService->buildChapter($book->id, [
            'first_chapter_prompt' => $book->first_chapter_prompt,
            'final_chapter' => false,
            'user_prompt' => null,
        ]);
    }
}
