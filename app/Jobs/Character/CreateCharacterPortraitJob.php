<?php

namespace App\Jobs\Character;

use App\Models\Character;
use App\Services\Character\CharacterPortraitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateCharacterPortraitJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Character $character
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CharacterPortraitService $portraitService): void
    {
        $portraitService->generatePortrait($this->character);
    }
}
