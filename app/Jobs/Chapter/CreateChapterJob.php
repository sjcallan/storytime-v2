<?php

namespace App\Jobs\Chapter;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateChapterJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected string $bookId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $chapterService = app(\App\Services\Chapter\ChapterService::class);
    }
}
