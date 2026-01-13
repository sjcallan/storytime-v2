<?php

namespace App\Jobs\Image;

use App\Models\Image;
use App\Services\Image\ImageGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 360;

    /**
     * Optional input image URLs for style consistency (e.g., character portraits).
     *
     * @var array<string>
     */
    public array $inputImageUrls;

    /**
     * Create a new job instance.
     *
     * @param  array<string>  $inputImageUrls
     */
    public function __construct(public Image $image, array $inputImageUrls = [])
    {
        $this->inputImageUrls = $inputImageUrls;
    }

    /**
     * Execute the job.
     */
    public function handle(ImageGenerationService $generationService): void
    {
        Log::info('[GenerateImageJob] Starting', [
            'image_id' => $this->image->id,
            'type' => $this->image->type->value,
            'book_id' => $this->image->book_id,
            'chapter_id' => $this->image->chapter_id,
            'character_id' => $this->image->character_id,
            'input_image_count' => count($this->inputImageUrls),
        ]);

        $generationService->generate($this->image, $this->inputImageUrls);

        Log::info('[GenerateImageJob] Completed', [
            'image_id' => $this->image->id,
            'status' => $this->image->fresh()->status->value,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[GenerateImageJob] Failed', [
            'image_id' => $this->image->id,
            'type' => $this->image->type->value,
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
        ]);

        // Mark the image as errored if not already
        if ($this->image->status->value !== 'error') {
            $this->image->update([
                'status' => 'error',
                'error' => $exception?->getMessage() ?? 'Unknown error during image generation',
            ]);
        }
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(15);
    }
}
