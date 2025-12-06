<?php

namespace App\Listeners\Chapter;

use App\Jobs\Chapter\CreateChapterImageJob;
use App\Jobs\Chapter\CreateChapterInlineImagesJob;
use App\Models\Chapter;

class CreateChapterImageListener
{
    /**
     * Handle the event.
     *
     * Dispatches jobs to generate chapter images asynchronously.
     * - CreateChapterImageJob: Generates the main chapter cover image
     * - CreateChapterInlineImagesJob: Generates inline/scene images (if scene prompts provided)
     */
    public function handle(object $event): void
    {
        $chapter = $event->chapter;

        if (! $chapter instanceof Chapter) {
            return;
        }

        // Dispatch main chapter image job if we have an image prompt
        if (! empty($chapter->image_prompt)) {
            CreateChapterImageJob::dispatch($chapter)->onQueue('images');
        }

        // Dispatch inline images job if scene prompts are provided in the event
        $scenePrompts = $event->scenePrompts ?? [];
        if (! empty($scenePrompts) && is_array($scenePrompts)) {
            CreateChapterInlineImagesJob::dispatch($chapter, $scenePrompts)->onQueue('images');
        }
    }
}
