<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateBookTitleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    protected $bookId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $bookId)
    {
        $this->bookId = $bookId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::debug('BOOK TITLE JOB');

        /** @var \App\Services\Book\BookService */
        $bookService = app(\App\Services\Book\BookService::class);
        $book = $bookService->getById($this->bookId);

        /** @var \App\Services\Builder\BookBuilderService */
        $builderService = app(\App\Services\Builder\BookBuilderService::class);

        $title = $builderService->getBookTitle($book->id);

        $bookService->updateById($book->id, [
            'title' => $title
        ], ['events' => false]);
        Log::debug('BOOK TITLE JOB COMPLETE');
    }
}
