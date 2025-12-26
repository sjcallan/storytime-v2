<?php

namespace App\Jobs\Chapter;

use App\Events\Chapter\ChapterInlineImagesCreatedEvent;
use App\Models\Chapter;
use App\Services\Builder\ChapterBuilderService;
use App\Services\Chapter\ChapterService;
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
    public function handle(ChapterBuilderService $chapterBuilderService, ChapterService $chapterService): void
    {
        Log::info('[RegenerateChapterInlineImageJob] Starting', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'image_index' => $this->imageIndex,
        ]);

        $inlineImages = $this->chapter->inline_images ?? [];

        $existingImage = null;
        foreach ($inlineImages as $image) {
            if (($image['paragraph_index'] ?? null) === $this->imageIndex) {
                $existingImage = $image;
                break;
            }
        }

        if (! $existingImage || empty($existingImage['prompt'])) {
            Log::warning('[RegenerateChapterInlineImageJob] No existing image or prompt found', [
                'chapter_id' => $this->chapter->id,
                'image_index' => $this->imageIndex,
            ]);

            return;
        }

        $scenePrompts = [
            [
                'paragraph_index' => $this->imageIndex,
                'prompt' => $existingImage['prompt'],
            ],
        ];

        $generatedImages = $chapterBuilderService->generateChapterImages(
            $this->chapter->book_id,
            $this->chapter->id,
            $scenePrompts
        );

        if (empty($generatedImages)) {
            Log::warning('[RegenerateChapterInlineImageJob] No image generated', [
                'chapter_id' => $this->chapter->id,
                'image_index' => $this->imageIndex,
            ]);

            return;
        }

        $newImage = $generatedImages[0];

        $updatedImages = [];
        $replaced = false;
        foreach ($inlineImages as $image) {
            if (($image['paragraph_index'] ?? null) === $this->imageIndex) {
                $updatedImages[] = [
                    'paragraph_index' => $this->imageIndex,
                    'url' => $newImage['url'],
                    'prompt' => $existingImage['prompt'],
                    'status' => 'complete',
                ];
                $replaced = true;
            } else {
                $updatedImages[] = $image;
            }
        }

        if (! $replaced) {
            $updatedImages[] = [
                'paragraph_index' => $this->imageIndex,
                'url' => $newImage['url'],
                'prompt' => $existingImage['prompt'],
                'status' => 'complete',
            ];
        }

        $chapterService->updateById($this->chapter->id, [
            'inline_images' => $updatedImages,
        ], ['events' => false]);

        Log::info('[RegenerateChapterInlineImageJob] Completed successfully', [
            'chapter_id' => $this->chapter->id,
            'image_index' => $this->imageIndex,
            'new_url' => $newImage['url'],
        ]);

        $this->chapter->refresh();
        ChapterInlineImagesCreatedEvent::dispatch($this->chapter);

        Log::info('[RegenerateChapterInlineImageJob] Dispatched inline images updated event', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
        ]);
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
