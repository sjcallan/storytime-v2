<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateBookCharactersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    protected $bookId;

    /** @var string */
    protected $summary;

    protected $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(string $bookId, string $summary)
    {
        $this->bookId = $bookId;
        $this->summary = $summary;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::debug('');
        Log::debug('GET BOOK CHARACTERS JOB');
        $bookService = app(\App\Services\Book\BookService::class);

        if(!$book = $bookService->getById($this->bookId, ['id', 'user_id', 'user_characters'])) {
            Log::debug('no user characters set. no book found for id: ' . $this->bookId);
            return;
        }

        if(!$book->user_characters || $book->user_characters == '') {
            Log::debug('no user characters set at the book level.');
            return;
        }

        /** @var \App\Services\Builder\CharacterBuilderService */
        $builder = app(\App\Services\Builder\CharacterBuilderService::class);
        
        if(!$characters = $builder->getBookCharacters($this->bookId, $book->user_characters)) {
            Log::debug('No charactes returned. exiting charater job');
        }

        $builder->saveCharacterResponse($characters, $this->bookId);
        
        return;
    }
}
