<?php

namespace App\Listeners\Chapter;

use App\Services\Builder\ChapterBuilderService;
use App\Services\Chapter\ChapterService;
use Illuminate\Support\Facades\Log;

class CreateChapterImageListener
{
    /**
     * Create the event listener.
     */
    public function __construct(protected ChapterBuilderService $chapterBuilderService, protected ChapterService $chapterService)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $chapter = $event->chapter;
        $image = $this->chapterBuilderService->getImage($chapter->book_id, $chapter->id, $chapter->image_prompt);

        $this->chapterService->updateById($chapter->id, [
            'image' => $image['image'],
            'image_prompt' => $this->chapterBuilderService->stripQuotes($image['image_prompt']),
        ], ['events' => false]);

        Log::debug('GET CHAPTER IMAGE JOB COMPLETE');

    }
}
