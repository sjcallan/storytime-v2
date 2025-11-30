<?php

namespace App\Listeners\Chapter;

use App\Jobs\Chapter\CreateFirstChapterJob;
use App\Models\Book;

class CreateFirstChapterListener
{
    /**
     * Handle the event.
     *
     * Only creates the first chapter when the book is ready (not in draft status
     * and has the required data like plot). This prevents chapter generation
     * from running during the multi-step book creation wizard.
     */
    public function handle(object $event): void
    {
        $book = $event->book;

        if (! $book instanceof Book) {
            return;
        }

        // Don't create chapters if there's no plot yet
        if (empty($book->plot)) {
            return;
        }

        // Don't create chapters if this book already has chapters
        if ($book->chapters()->exists()) {
            return;
        }

        dispatch(new CreateFirstChapterJob($book->id));
    }
}
