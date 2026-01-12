<?php

namespace App\Jobs\Chapter;

use App\Events\Chapter\ChapterUpdatedEvent;
use App\Events\Image\ImageGeneratedEvent;
use App\Models\Chapter;
use App\Services\Builder\ChapterBuilderService;
use App\Services\Chapter\ChapterService;
use App\Services\Image\ImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateChapterImageJob implements ShouldQueue
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
    public function handle(
        ChapterBuilderService $chapterBuilderService,
        ChapterService $chapterService,
        ImageService $imageService
    ): void {
        Log::info('[CreateChapterImageJob] Starting', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
        ]);

        // Get or create Image record for this chapter header
        $imageRecord = $imageService->getOrCreateChapterHeader($this->chapter);

        if (empty($imageRecord->prompt)) {
            Log::warning('[CreateChapterImageJob] No image prompt found, skipping', [
                'chapter_id' => $this->chapter->id,
            ]);

            return;
        }

        $imageService->markProcessing($imageRecord);

        try {
            $image = $chapterBuilderService->getImage(
                $this->chapter->book_id,
                $this->chapter->id,
                $imageRecord->prompt
            );

            if (! empty($image['image'])) {
                // Update chapter to point to new image record
                $chapterService->updateById($this->chapter->id, [
                    'header_image_id' => $imageRecord->id,
                ], ['events' => false]);

                // Update Image record with the URL
                $imageService->markComplete($imageRecord, $image['image']);

                Log::info('[CreateChapterImageJob] Completed successfully', [
                    'chapter_id' => $this->chapter->id,
                    'image_id' => $imageRecord->id,
                    'has_image' => true,
                ]);

                // Broadcast update via websocket so frontend can display the new image
                $this->chapter->refresh();
                ChapterUpdatedEvent::dispatch($this->chapter);
                event(new ImageGeneratedEvent($imageRecord->fresh()));

                Log::info('[CreateChapterImageJob] Dispatched events', [
                    'chapter_id' => $this->chapter->id,
                    'book_id' => $this->chapter->book_id,
                    'image_id' => $imageRecord->id,
                ]);
            } else {
                $imageService->markError($imageRecord, 'Image generation returned empty result');
                event(new ImageGeneratedEvent($imageRecord->fresh()));

                Log::error('[CreateChapterImageJob] Image generation failed', [
                    'chapter_id' => $this->chapter->id,
                    'image_id' => $imageRecord->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[CreateChapterImageJob] Failed', [
                'chapter_id' => $this->chapter->id,
                'image_id' => $imageRecord->id,
                'error' => $e->getMessage(),
            ]);

            $imageService->markError($imageRecord, $e->getMessage());
            event(new ImageGeneratedEvent($imageRecord->fresh()));

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[CreateChapterImageJob] Job failed', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
        ]);
    }
}
