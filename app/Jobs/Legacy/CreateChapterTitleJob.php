<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateChapterTitleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    protected $chapterId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $chapterId)
    {
        $this->chapterId = $chapterId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::debug('GET CHAPTER TITLE JOB');
        $chapterService = app(\App\Services\Chapter\ChapterService::class);

        if(!$chapter = $chapterService->getById($this->chapterId, ['id', 'book_id', 'user_id', 'summary'], ['with' => ['book']])) {
            Log::debug('no chapter for id ' . $this->chapterId);
            return;
        }

        /** @var \App\Services\Builder\ChapterBuilderService */
        $builder = app(\App\Services\Builder\ChapterBuilderService::class);
        $title = $builder->getChapterTitle($chapter->book_id, $chapter->id, $chapter->user_id, $chapter->summary);

        $chapterService->updateById($this->chapterId, [
            'title' => $title['completion']
        ], ['events' => false]);
        Log::debug('GET CHAPTER TITLE JOB COMPLETE');
        
        return;
    }
}
