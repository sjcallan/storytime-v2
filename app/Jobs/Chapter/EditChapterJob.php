<?php

namespace App\Jobs\Chapter;

use App\Models\Chapter;
use App\Services\Builder\ChapterBuilderService;
use App\Services\Chapter\ChapterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EditChapterJob implements ShouldQueue
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
    public function __construct(
        protected Chapter $chapter,
        protected string $instructions
    ) {
        Log::info('[EditChapterJob] Job constructed', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'instructions_length' => strlen($this->instructions),
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(ChapterBuilderService $chapterBuilderService, ChapterService $chapterService): void
    {
        $jobStartTime = microtime(true);

        Log::info('[EditChapterJob] Job started', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Refresh chapter to get latest data
            $this->chapter->refresh();

            // Mark chapter as processing
            $chapterService->updateById($this->chapter->id, [
                'status' => 'editing',
            ], ['events' => false]);

            // Get edited content from AI
            $editedContent = $chapterBuilderService->editChapterContent(
                $this->chapter,
                $this->instructions
            );

            if (empty($editedContent['body'])) {
                Log::error('[EditChapterJob] Empty body returned from edit', [
                    'chapter_id' => $this->chapter->id,
                ]);
                throw new \RuntimeException('Empty chapter body returned from AI edit');
            }

            // Update chapter with new content
            $updateData = [
                'body' => $editedContent['body'],
                'summary' => $editedContent['summary'] ?? $this->chapter->summary,
                'status' => 'complete',
            ];

            $sceneImages = $editedContent['scene_images'] ?? [];

            Log::info('[EditChapterJob] Updating chapter with edited content', [
                'chapter_id' => $this->chapter->id,
                'body_length' => strlen($updateData['body']),
                'scene_images_count' => count($sceneImages),
            ]);

            $chapterService->updateById($this->chapter->id, $updateData);

            // Dispatch inline images generation as a background job
            if (! empty($sceneImages) && is_array($sceneImages)) {
                Log::info('[EditChapterJob] Dispatching inline images job', [
                    'chapter_id' => $this->chapter->id,
                    'scene_count' => count($sceneImages),
                ]);

                CreateChapterInlineImagesJob::dispatch($this->chapter->fresh(), $sceneImages)->onQueue('images');
            }

            $jobDuration = microtime(true) - $jobStartTime;

            Log::info('[EditChapterJob] Job completed successfully', [
                'chapter_id' => $this->chapter->id,
                'book_id' => $this->chapter->book_id,
                'duration_seconds' => round($jobDuration, 2),
            ]);
        } catch (Throwable $e) {
            $jobDuration = microtime(true) - $jobStartTime;

            // Mark chapter as complete with error (revert to previous state)
            $chapterService->updateById($this->chapter->id, [
                'status' => 'complete',
                'error' => $e->getMessage(),
            ], ['events' => false]);

            Log::error('[EditChapterJob] Job failed with exception', [
                'chapter_id' => $this->chapter->id,
                'book_id' => $this->chapter->book_id,
                'attempt' => $this->attempts(),
                'duration_seconds' => round($jobDuration, 2),
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::critical('[EditChapterJob] Job permanently failed', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
        ]);
    }
}
