<?php

namespace App\Listeners\Chapter;

use App\Jobs\Chapter\CreateChapterImageJob;
use App\Jobs\Chapter\CreateChapterInlineImagesJob;
use App\Models\Chapter;
use Illuminate\Support\Facades\Log;

class CreateChapterImageListener
{
    /**
     * Handle the event.
     *
     * Dispatches jobs to generate chapter images asynchronously.
     * - CreateChapterImageJob: Generates the main chapter header image
     * - CreateChapterInlineImagesJob: Generates inline/scene images (if scene prompts provided)
     *
     * Only triggers when:
     * - Chapter status is 'complete'
     * - Chapter has an image_prompt
     * - Chapter doesn't already have an image
     */
    public function handle(object $event): void
    {
        $chapter = $event->chapter;

        if (! $chapter instanceof Chapter) {
            return;
        }

        // Only process complete chapters that have an image prompt but no image yet
        if ($chapter->status !== 'complete') {
            return;
        }

        // Dispatch main chapter header image job if we have an image prompt but no image
        if (! empty($chapter->image_prompt) && empty($chapter->image)) {
            Log::info('[CreateChapterImageListener] Dispatching chapter header image job', [
                'chapter_id' => $chapter->id,
                'book_id' => $chapter->book_id,
            ]);

            CreateChapterImageJob::dispatch($chapter)->onQueue('images');
        }

        // Dispatch inline images job if scene prompts are provided in the event
        $scenePrompts = $event->scenePrompts ?? [];
        if (! empty($scenePrompts) && is_array($scenePrompts)) {
            CreateChapterInlineImagesJob::dispatch($chapter, $scenePrompts)->onQueue('images');
        }
    }
}
