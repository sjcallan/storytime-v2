<?php

namespace App\Jobs\Book;

use App\Services\Book\BookCoverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateBookCoverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 360;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $bookId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(BookCoverService $coverService): void
    {
        Log::info('GenerateBookCoverJob: Starting', ['book_id' => $this->bookId]);

        $success = $coverService->generateCoverForBook($this->bookId);

        if ($success) {
            Log::info('GenerateBookCoverJob: Completed successfully', ['book_id' => $this->bookId]);
        } else {
            Log::error('GenerateBookCoverJob: Failed', ['book_id' => $this->bookId]);
        }
    }
}
