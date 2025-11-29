<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetChapterImageJob implements ShouldQueue
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
        Log::debug('GET CHAPTER IMAGE JOB');
        $chapterService = app(\App\Services\Chapter\ChapterService::class);

        if(!$chapter = $chapterService->getById($this->chapterId, ['id', 'book_id', 'summary'], ['with' => ['book']])) {
            Log::debug('no chapter for id ' . $this->chapterId);
            return;
        }

        // if($chapter->book->age_level >= 18) {
        //     return;
        // }

        /** @var \App\Services\Builder\BuilderService */
        $builder = app(\App\Services\Builder\BuilderService::class);
        $image = $builder->getImage($chapter->book_id, $chapter->id, $chapter->summary);
        Log::debug('image response: ' . json_encode($image));

        $chapterService->updateById($this->chapterId, [
            'image' => $image['image'],
            'image_prompt' => $builder->stripQuotes($image['image_prompt'])
        ], ['events' => false]);
        Log::debug('GET CHAPTER IMAGE JOB COMPLETE');
        
        return;
    }
}
