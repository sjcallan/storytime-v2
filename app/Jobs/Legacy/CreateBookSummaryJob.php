<?php

namespace App\Jobs;

use App\Events\Chapter\ChapterSummarySavedEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateBookSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    protected $chapterId;

    /** @var string */
    protected $summary;

    /**
     * Create a new job instance.
     */
    public function __construct(string $chapterId, string $summary)
    {
        $this->chapterId = $chapterId;
        $this->summary = $summary;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::debug('GET BOOK SUMMARY JOB');
        $chapterService = app(\App\Services\Chapter\ChapterService::class);

        if(!$chapter = $chapterService->getById($this->chapterId, ['id', 'book_id', 'user_id'], ['with' => ['book']])) {
            Log::debug('no chapter for id ' . $this->chapterId);
            return;
        }

        /** @var \App\Services\Builder\ChapterBuilderService */
        $builder = app(\App\Services\Builder\ChapterBuilderService::class);
        if(!$bookSummary = $builder->getBookSummary($chapter->book_id, $chapter->id, $chapter->user_id, $this->summary)) {
            return;
        }

        $chapter = $chapterService->updateById($this->chapterId, [
            'book_summary' => $bookSummary['completion']
        ], ['events' => false]);

        Log::debug('GET BOOK SUMMARY JOB COMPLETE');
        
        return;
    }
}
