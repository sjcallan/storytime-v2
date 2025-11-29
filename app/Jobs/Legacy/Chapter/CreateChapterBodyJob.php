<?php

namespace App\Jobs\Chapter;

use App\Services\Builder\ChapterBuilderService;
use App\Services\Chapter\ChapterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateChapterBodyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected string $chapterId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ChapterService $chapterService, ChapterBuilderService $chapterBuilderService): void
    {
        $chapter = $chapterService->getById($this->chapterId);
        $body = $chapterBuilderService->getNextChapterResponse($chapter->book_id, [
            'user_prompt' => $chapter->user_prompt,
            'final_chapter' => $chapter->final_chapter
        ]);

        $chapterService->updateById($this->chapterId, [
            'body' => $body
        ]);
    }
}
