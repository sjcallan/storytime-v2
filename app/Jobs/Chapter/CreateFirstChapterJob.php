<?php

namespace App\Jobs\Chapter;

use App\Services\Book\BookService;
use App\Services\Builder\ChapterBuilderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateFirstChapterJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(protected string $bookId)
    {
        Log::info('[CreateFirstChapterJob] Job constructed', [
            'book_id' => $this->bookId,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(ChapterBuilderService $chapterBuilderService, BookService $bookService): void
    {
        $jobStartTime = microtime(true);

        Log::info('[CreateFirstChapterJob] Job started', [
            'book_id' => $this->bookId,
            'attempt' => $this->attempts(),
        ]);

        try {
            Log::debug('[CreateFirstChapterJob] Fetching book from database', [
                'book_id' => $this->bookId,
            ]);

            $book = $bookService->getById($this->bookId);

            if (! $book) {
                Log::error('[CreateFirstChapterJob] Book not found', [
                    'book_id' => $this->bookId,
                ]);

                return;
            }

            Log::debug('[CreateFirstChapterJob] Book fetched successfully', [
                'book_id' => $book->id,
                'book_type' => $book->type ?? 'unknown',
                'book_genre' => $book->genre ?? 'unknown',
                'has_first_chapter_prompt' => ! empty($book->first_chapter_prompt),
            ]);

            $chapterData = [
                'final_chapter' => false,
                'user_prompt' => $book->first_chapter_prompt,
            ];

            Log::info('[CreateFirstChapterJob] Calling buildChapter', [
                'book_id' => $book->id,
                'chapter_data' => $chapterData,
            ]);

            $chapter = $chapterBuilderService->buildChapter($book->id, $chapterData);

            $jobDuration = microtime(true) - $jobStartTime;

            Log::info('[CreateFirstChapterJob] Job completed successfully', [
                'book_id' => $book->id,
                'chapter_id' => $chapter->id ?? 'unknown',
                'duration_seconds' => round($jobDuration, 2),
            ]);
        } catch (Throwable $e) {
            $jobDuration = microtime(true) - $jobStartTime;

            Log::error('[CreateFirstChapterJob] Job failed with exception', [
                'book_id' => $this->bookId,
                'attempt' => $this->attempts(),
                'duration_seconds' => round($jobDuration, 2),
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::critical('[CreateFirstChapterJob] Job permanently failed after all retries', [
            'book_id' => $this->bookId,
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
            'exception_file' => $exception?->getFile() ?? 'unknown',
            'exception_line' => $exception?->getLine() ?? 'unknown',
        ]);
    }
}
