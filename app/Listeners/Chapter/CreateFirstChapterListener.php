<?php

namespace App\Listeners\Chapter;

use App\Jobs\Chapter\CreateFirstChapterJob;
use App\Models\Book;

class CreateFirstChapterListener
{
    /**
     * Handle the event.
     *
     * Only creates the first chapter when the book is ready (status is 'in_progress'
     * and has the required data like plot). This prevents chapter generation
     * from running during the multi-step book creation wizard, ensuring all
     * character additions, removals, and edits are complete before generation.
     */
    public function handle(object $event): void
    {
        $book = $event->book;

        if (! $book instanceof Book) {
            return;
        }

        // Only create chapter when book status changes to 'in_progress'
        // This ensures all character edits are complete (step 4 finished)
        if ($book->status !== 'in_progress') {
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
