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
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ChapterBuilderService $chapterBuilderService,
        ChapterService $chapterService,
        ImageService $imageService
    ): void {
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

        // Create Image records for each scene prompt
        $imageRecords = [];
        $pendingInlineImages = [];
        foreach ($this->scenePrompts as $scenePrompt) {
            $paragraphIndex = (int) $scenePrompt['paragraph_index'];
            $prompt = $scenePrompt['prompt'];

            // Check for existing image at this paragraph index
            $existingImage = $imageService->getInlineImageByParagraphIndex($this->chapter->id, $paragraphIndex);

            if ($existingImage) {
                $imageService->resetForRegeneration($existingImage);
                $imageService->markProcessing($existingImage);
                $imageService->updateById($existingImage->id, ['prompt' => $prompt]);
                $imageRecords[$paragraphIndex] = $existingImage;
            } else {
                $imageRecord = $imageService->createChapterInlineImage($this->chapter, $paragraphIndex, $prompt);
                $imageService->markProcessing($imageRecord);
                $imageRecords[$paragraphIndex] = $imageRecord;
            }

            // Build pending inline_images with image_id references
            $pendingInlineImages[] = [
                'image_id' => $imageRecords[$paragraphIndex]->id,
                'paragraph_index' => $paragraphIndex,
            ];
        }

        // Update chapter with pending inline images using image_id references
        $chapterService->updateById($this->chapter->id, [
            'inline_images' => $pendingInlineImages,
        ], ['events' => false]);

        $inlineImages = $chapterBuilderService->generateChapterImages(
            $this->chapter->book_id,
            $this->chapter->id,
            $this->scenePrompts
        );

        // Update Image records with generated URLs, full prompts, and build the inline_images array
        $inlineImagesJson = [];
        foreach ($inlineImages as $inlineImage) {
            $paragraphIndex = (int) $inlineImage['paragraph_index'];

            if (isset($imageRecords[$paragraphIndex])) {
                $imageRecord = $imageRecords[$paragraphIndex];

                if (! empty($inlineImage['url']) && ($inlineImage['status'] ?? 'complete') === 'complete') {
                    $imageService->markComplete($imageRecord, $inlineImage['url']);
                    // Save the full prompt (JSON schema) that was actually sent to Replicate
                    if (! empty($inlineImage['prompt'])) {
                        $imageService->updateById($imageRecord->id, ['prompt' => $inlineImage['prompt']]);
                    }
                } else {
                    $imageService->markError($imageRecord, $inlineImage['error'] ?? 'Image generation failed');
                }

                // Build the inline_images JSON with image_id references
                $inlineImagesJson[] = [
                    'image_id' => $imageRecord->id,
                    'paragraph_index' => $paragraphIndex,
                ];

                event(new ImageGeneratedEvent($imageRecord->fresh()));
            }
        }

        // Update chapter with inline images using image_id references
        $chapterService->updateById($this->chapter->id, [
            'inline_images' => ! empty($inlineImagesJson) ? $inlineImagesJson : null,
        ], ['events' => false]);

        Log::info('[CreateChapterInlineImagesJob] Completed successfully', [
            'chapter_id' => $this->chapter->id,
            'images_generated' => count($inlineImages),
        ]);

        if (! empty($inlineImages)) {
            $this->chapter->refresh();
            ChapterInlineImagesCreatedEvent::dispatch($this->chapter);

            Log::info('[CreateChapterInlineImagesJob] Dispatched inline images created event', [
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
        Log::error('[CreateChapterInlineImagesJob] Job failed', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'scene_count' => count($this->scenePrompts),
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
        ]);
    }
}
