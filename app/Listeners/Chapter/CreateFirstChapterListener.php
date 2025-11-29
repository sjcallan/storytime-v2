<?php

namespace App\Listeners\Chapter;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateFirstChapterListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $book = $event->book;

        if(!$book) {
            return;
        }

        $chapterBuilderService = app(\App\Services\Builder\ChapterBuilderService::class);
        $chapter = $chapterBuilderService->buildChapter($book->id, [
            'first_chapter_prompt' => $book->first_chapter_prompt
        ]);

    }
}
