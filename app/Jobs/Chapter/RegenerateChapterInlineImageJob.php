<?php

namespace App\Jobs\Chapter;

use App\Events\Chapter\ChapterInlineImagesCreatedEvent;
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

class RegenerateChapterInlineImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Chapter $chapter,
        public int $imageIndex
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ChapterBuilderService $chapterBuilderService,
        ChapterService $chapterService,
        ImageService $imageService
    ): void {
        Log::info('[RegenerateChapterInlineImageJob] Starting', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'image_index' => $this->imageIndex,
        ]);

        // Find existing Image record from the images table
        $existingImage = $imageService->getInlineImageByParagraphIndex(
            $this->chapter->id,
            $this->imageIndex
        );

        if (! $existingImage || empty($existingImage->prompt)) {
            Log::warning('[RegenerateChapterInlineImageJob] No existing image or prompt found', [
                'chapter_id' => $this->chapter->id,
                'image_index' => $this->imageIndex,
            ]);

            return;
        }

        $prompt = $existingImage->prompt;

        // Always create a NEW Image record for regeneration to preserve history
        $imageRecord = $imageService->createChapterInlineImage(
            $this->chapter,
            $this->imageIndex,
            $prompt
        );
        $imageService->markProcessing($imageRecord);

        // Update the inline_images JSON to reference the new Image record
        $this->updateInlineImagesReference($chapterService, $imageRecord->id);

        $scenePrompts = [
            [
                'paragraph_index' => $this->imageIndex,
                'prompt' => $prompt,
            ],
        ];

        try {
            $generatedImages = $chapterBuilderService->generateChapterImages(
                $this->chapter->book_id,
                $this->chapter->id,
                $scenePrompts
            );

            if (empty($generatedImages)) {
                Log::warning('[RegenerateChapterInlineImageJob] No image generated', [
                    'chapter_id' => $this->chapter->id,
                    'image_index' => $this->imageIndex,
                    'image_id' => $imageRecord->id,
                ]);

                $imageService->markError($imageRecord, 'No image generated');
                event(new ImageGeneratedEvent($imageRecord->fresh()));

                return;
            }

            $newImage = $generatedImages[0];

            // Update Image record
            if (! empty($newImage['url'])) {
                $imageService->markComplete($imageRecord, $newImage['url']);
            } else {
                $imageService->markError($imageRecord, 'Image generation returned empty URL');
            }

            event(new ImageGeneratedEvent($imageRecord->fresh()));

            Log::info('[RegenerateChapterInlineImageJob] Completed successfully', [
                'chapter_id' => $this->chapter->id,
                'image_index' => $this->imageIndex,
                'image_id' => $imageRecord->id,
                'new_url' => $newImage['url'] ?? null,
            ]);

            $this->chapter->refresh();
            ChapterInlineImagesCreatedEvent::dispatch($this->chapter);

            Log::info('[RegenerateChapterInlineImageJob] Dispatched events', [
                'chapter_id' => $this->chapter->id,
                'book_id' => $this->chapter->book_id,
                'image_id' => $imageRecord->id,
            ]);
        } catch (\Exception $e) {
            Log::error('[RegenerateChapterInlineImageJob] Failed', [
                'chapter_id' => $this->chapter->id,
                'image_index' => $this->imageIndex,
                'image_id' => $imageRecord->id,
                'error' => $e->getMessage(),
            ]);

            $imageService->markError($imageRecord, $e->getMessage());
            event(new ImageGeneratedEvent($imageRecord->fresh()));

            throw $e;
        }
    }

    /**
     * Update the inline_images JSON to reference the new Image record.
     */
    protected function updateInlineImagesReference(ChapterService $chapterService, string $newImageId): void
    {
        $inlineImages = $this->chapter->inline_images ?? [];
        $updatedImages = [];
        $replaced = false;

        foreach ($inlineImages as $image) {
            if (($image['paragraph_index'] ?? null) === $this->imageIndex) {
                $updatedImages[] = [
                    'image_id' => $newImageId,
                    'paragraph_index' => $this->imageIndex,
                ];
                $replaced = true;
            } else {
                $updatedImages[] = $image;
            }
        }

        if (! $replaced) {
            $updatedImages[] = [
                'image_id' => $newImageId,
                'paragraph_index' => $this->imageIndex,
            ];
        }

        $chapterService->updateById($this->chapter->id, [
            'inline_images' => $updatedImages,
        ], ['events' => false]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[RegenerateChapterInlineImageJob] Job failed', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'image_index' => $this->imageIndex,
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
        ]);
    }
}
