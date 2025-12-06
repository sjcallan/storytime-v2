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

        if (! $book instanceof Book) {
            return;
        }

        // Only generate cover when book moves from draft to in_progress
        if ($book->getOriginal('status') !== 'draft' || $book->status !== 'in_progress') {
            return;
        }

        // Don't generate if there's no plot yet
        if (empty($book->plot)) {
            return;
        }

        // Don't generate if cover is already being generated or already exists
        if (in_array($book->cover_image_status, ['pending', 'complete'])) {
            return;
        }

        // Don't regenerate if book already has a title (cover was already generated)
        if (! empty($book->title) && $book->title !== 'Untitled Story') {
            return;
        }

        dispatch(new GenerateBookCoverJob($book->id))->onQueue('images');
    }
}
