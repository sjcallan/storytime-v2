<?php

namespace App\Listeners\Book;

use App\Services\Book\BookService;

class CreateBookMetaDataListener
{
    /**
     * Create the event listener.
     */
    public function __construct(protected BookService $bookService)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $book = $event->book;

        if (! $book) {
            return;
        }

        if (! $book->getOriginal('plot') && $book->plot) {
            $this->bookService->createBookMetaDataByBookId($book->id);
        }
    }
}
