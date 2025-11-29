<?php

namespace App\Jobs\Book;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateBookMetaDataJob implements ShouldQueue
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
    public function handle(): void
    {
        $bookService = app(\App\Services\Book\BookService::class);
        $book = $bookService->getById($this->bookId);

        if(!$book) {
            return;
        }

        $bookService->createBookMetaDataByBookId($book->id);
    }
}
