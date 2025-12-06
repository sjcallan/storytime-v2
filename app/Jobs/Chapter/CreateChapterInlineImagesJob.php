<?php

namespace App\Jobs\Chapter;

use App\Models\Chapter;
use App\Services\Builder\ChapterBuilderService;
use App\Services\Chapter\ChapterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateChapterInlineImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  array<array{paragraph_index: int|string, prompt: string}>  $scenePrompts
     */
    public function __construct(
        public Chapter $chapter,
        public array $scenePrompts
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ChapterBuilderService $chapterBuilderService, ChapterService $chapterService): void
    {
        Log::info('[CreateChapterInlineImagesJob] Starting', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'scene_count' => count($this->scenePrompts),
        ]);

        if (empty($this->scenePrompts)) {
            Log::warning('[CreateChapterInlineImagesJob] No scene prompts provided, skipping', [
                'chapter_id' => $this->chapter->id,
            ]);

            return;
        }

        $inlineImages = $chapterBuilderService->generateChapterImages(
            $this->chapter->book_id,
            $this->chapter->id,
            $this->scenePrompts
        );

        $chapterService->updateById($this->chapter->id, [
            'inline_images' => ! empty($inlineImages) ? $inlineImages : null,
        ], ['events' => false]);

        Log::info('[CreateChapterInlineImagesJob] Completed successfully', [
            'chapter_id' => $this->chapter->id,
            'images_generated' => count($inlineImages),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[CreateChapterInlineImagesJob] Job failed', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'scene_count' => count($this->scenePrompts),
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
        ]);
    }
}
