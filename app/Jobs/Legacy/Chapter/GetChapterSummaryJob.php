<?php

namespace App\Jobs\Chapter;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetChapterSummaryJob implements ShouldQueue
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
    public function handle(): void
    {
        Log::debug('GET CHAPTER SUMMARY JOB');
        $chapterService = app(\App\Services\Chapter\ChapterService::class);

        if(!$chapter = $chapterService->getById($this->chapterId, ['id', 'book_id', 'user_id', 'body'], ['with' => ['book']])) {
            Log::debug('no chapter for id ' . $this->chapterId);
            return;
        }

        /** @var \App\Services\Builder\ChapterBuilderService */
        $builder = app(\App\Services\Builder\ChapterBuilderService::class);
        $summary = $builder->getSummary($chapter->book_id, $chapter->body);

        $chapterService->updateById($this->chapterId, [
            'summary' => $summary['completion']
        ], ['events' => false]);
        Log::debug('GET CHAPTER SUMMARY JOB COMPLETE');
        
        return;
    }
}
