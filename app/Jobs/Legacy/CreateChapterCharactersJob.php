<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateChapterCharactersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    protected $chapterId;

    protected $tries = 2;

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
        Log::debug('');
        Log::debug('GET CHAPTER CHARACTERS JOB');
        $chapterService = app(\App\Services\Chapter\ChapterService::class);

        if(!$chapter = $chapterService->getById($this->chapterId, ['id', 'book_id', 'user_id', 'summary'], ['with' => ['book']])) {
            return;
        }

        /** @var \App\Services\Builder\CharacterBuilderService */
        $builder = app(\App\Services\Builder\CharacterBuilderService::class);
        
        if(!$characters = $builder->getChapterCharacters($this->chapterId, $chapter->summary)) {
            Log::debug('No charactes returned. exiting charater job');
        }

        $builder->saveCharacterResponse($characters, $chapter->book_id, $this->chapterId);
        
        return;
    }
}
