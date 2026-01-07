<?php

namespace App\Jobs\Chapter;

use App\Events\Chapter\ChapterUpdatedEvent;
use App\Models\Chapter;
use App\Services\Builder\ChapterBuilderService;
use App\Services\Chapter\ChapterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegenerateChapterHeaderImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(public Chapter $chapter) {}

    /**
     * Execute the job.
     */
    public function handle(ChapterBuilderService $chapterBuilderService, ChapterService $chapterService): void
    {
        Log::info('[RegenerateChapterHeaderImageJob] Starting', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
        ]);

        if (empty($this->chapter->image_prompt)) {
            Log::warning('[RegenerateChapterHeaderImageJob] No image prompt found, skipping', [
                'chapter_id' => $this->chapter->id,
            ]);

            return;
        }

        $image = $chapterBuilderService->getImage(
            $this->chapter->book_id,
            $this->chapter->id,
            $this->chapter->image_prompt
        );

        $chapterService->updateById($this->chapter->id, [
            'image' => $image['image'],
        ], ['events' => false]);

        Log::info('[RegenerateChapterHeaderImageJob] Completed successfully', [
            'chapter_id' => $this->chapter->id,
            'has_image' => ! empty($image['image']),
        ]);

        if (! empty($image['image'])) {
            $this->chapter->refresh();
            ChapterUpdatedEvent::dispatch($this->chapter);

            Log::info('[RegenerateChapterHeaderImageJob] Dispatched chapter updated event', [
                'chapter_id' => $this->chapter->id,
                'book_id' => $this->chapter->book_id,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[RegenerateChapterHeaderImageJob] Job failed', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
        ]);
    }
}
