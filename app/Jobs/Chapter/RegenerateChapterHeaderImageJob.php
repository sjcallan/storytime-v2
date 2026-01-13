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

class RegenerateChapterHeaderImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     * Set to 10 minutes to allow for polling when Replicate's sync wait times out.
     */
    public int $timeout = 600;

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
        Log::info('[RegenerateChapterHeaderImageJob] Starting', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
        ]);

        // Get the prompt from the existing header image
        $existingImage = $imageService->getChapterHeader($this->chapter->id);
        $existingImageId = $existingImage?->id;
        $prompt = $existingImage?->prompt;

        if (empty($prompt)) {
            Log::warning('[RegenerateChapterHeaderImageJob] No image prompt found, skipping', [
                'chapter_id' => $this->chapter->id,
            ]);

            return;
        }

        // Always create a NEW Image record for regeneration to preserve history
        $imageRecord = $imageService->createChapterHeaderImage($this->chapter, $prompt);
        $imageService->markProcessing($imageRecord);

        // Update chapter to point to the new image record
        $chapterService->updateById($this->chapter->id, [
            'header_image_id' => $imageRecord->id,
        ], ['events' => false]);

        try {
            // Generate the image using FLUX 2 schema with character identification
            Log::info('[RegenerateChapterHeaderImageJob] Starting image generation with FLUX 2 schema', [
                'chapter_id' => $this->chapter->id,
                'image_id' => $imageRecord->id,
            ]);

            $image = $chapterBuilderService->generateHeaderImage(
                $this->chapter->book_id,
                $this->chapter->id,
                $prompt
            );

            if (! empty($image['image'])) {
                // Update chapter to point to new image record
                $chapterService->updateById($this->chapter->id, [
                    'header_image_id' => $imageRecord->id,
                ], ['events' => false]);

                // Update Image record with the URL
                $imageService->markComplete($imageRecord, $image['image']);

                Log::info('[RegenerateChapterHeaderImageJob] Completed successfully', [
                    'chapter_id' => $this->chapter->id,
                    'image_id' => $imageRecord->id,
                    'has_image' => true,
                ]);

                $this->chapter->refresh();
                ChapterUpdatedEvent::dispatch($this->chapter);
                event(new ImageGeneratedEvent($imageRecord->fresh()));

                Log::info('[RegenerateChapterHeaderImageJob] Dispatched events', [
                    'chapter_id' => $this->chapter->id,
                    'book_id' => $this->chapter->book_id,
                    'image_id' => $imageRecord->id,
                ]);

                // Soft delete the original image after successful regeneration
                if ($existingImageId) {
                    $imageService->deleteById($existingImageId);
                    Log::info('[RegenerateChapterHeaderImageJob] Soft deleted original image', [
                        'chapter_id' => $this->chapter->id,
                        'deleted_image_id' => $existingImageId,
                    ]);
                }
            } else {
                $imageService->markError($imageRecord, 'Image generation returned empty result');
                event(new ImageGeneratedEvent($imageRecord->fresh()));

                Log::error('[RegenerateChapterHeaderImageJob] Image generation failed', [
                    'chapter_id' => $this->chapter->id,
                    'image_id' => $imageRecord->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[RegenerateChapterHeaderImageJob] Failed', [
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
        Log::error('[RegenerateChapterHeaderImageJob] Job failed', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
        ]);
    }
}
