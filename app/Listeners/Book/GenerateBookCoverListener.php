<?php

namespace App\Listeners\Book;

use App\Jobs\Book\GenerateBookCoverJob;
use App\Models\Book;

class GenerateBookCoverListener
{
    /**
     * Handle the event.
     *
     * Generates book cover when the book transitions from draft to in_progress status
     * and has the required data (plot, title not already set).
     */
    public function handle(object $event): void
    {
        $book = $event->book;

        // Don't generate if cover is already being generated or already exists
        if (in_array($book->cover_image_status, ['pending', 'complete'])) {
            return;
        }

        GenerateBookCoverJob::dispatch($book->id)->onQueue('images');
    }
}
