<?php

namespace App\Listeners\Chapter;

use App\Events\Chapter\ChapterUpdatedEvent;
use App\Jobs\Chapter\ExtractChapterCharactersJob;
use Illuminate\Support\Facades\Log;

class ExtractChapterCharactersListener
{
    /**
     * Handle the chapter updated event.
     *
     * Extracts new characters when a chapter is completed (status changes to 'complete').
     * This ensures character extraction only happens after the chapter content is fully generated.
     */
    public function handle(ChapterUpdatedEvent $event): void
    {
        $chapter = $event->chapter;
        $originalData = $chapter->getOriginal();

        $previousStatus = $originalData['status'] ?? null;
        $currentStatus = $chapter->status;

        if ($previousStatus === $currentStatus) {
            return;
        }

        if ($currentStatus !== 'complete') {
            return;
        }

        if (empty($chapter->body)) {
            Log::debug('[ExtractChapterCharactersListener] Chapter has no body, skipping extraction', [
                'chapter_id' => $chapter->id,
            ]);

            return;
        }

        Log::info('[ExtractChapterCharactersListener] Chapter completed, dispatching character extraction', [
            'chapter_id' => $chapter->id,
            'book_id' => $chapter->book_id,
            'previous_status' => $previousStatus,
            'current_status' => $currentStatus,
        ]);

        ExtractChapterCharactersJob::dispatch($chapter)->onQueue('default');
    }
}

