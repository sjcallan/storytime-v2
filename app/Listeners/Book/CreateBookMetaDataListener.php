<?php

namespace App\Listeners\Book;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateBookMetaDataListener
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

        $bookService = app(\App\Services\Book\BookService::class);
        $bookService->createBookMetaDataByBookId($book->id);
    }
}
