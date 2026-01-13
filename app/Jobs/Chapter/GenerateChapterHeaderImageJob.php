<?php

namespace App\Jobs\Chapter;

use App\Events\Chapter\ChapterUpdatedEvent;
use App\Events\Image\ImageGeneratedEvent;
use App\Models\Chapter;
use App\Services\Builder\ChapterBuilderService;
use App\Services\Chapter\ChapterService;
use App\Services\Image\ImageService;
use App\Services\OpenAI\ChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateChapterHeaderImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     * Set to 10 minutes to allow for polling when Replicate's sync wait times out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(public Chapter $chapter) {}

    /**
     * Execute the job.
     */
    public function handle(
        ChapterBuilderService $chapterBuilderService,
        ChapterService $chapterService,
        ChatService $chatService,
        ImageService $imageService
    ): void {
        Log::info('[GenerateChapterHeaderImageJob] Starting', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
        ]);

        $book = $this->chapter->book;

        if (! $book) {
            Log::error('[GenerateChapterHeaderImageJob] Book not found', [
                'chapter_id' => $this->chapter->id,
            ]);

            return;
        }

        // Get or create Image record for this chapter header
        $imageRecord = $imageService->getOrCreateChapterHeader($this->chapter);
        $imageService->markProcessing($imageRecord);

        // Determine chapter label based on book type
        $chapterLabel = in_array($book->type, ['theatre', 'screenplay']) ? 'scene' : 'chapter';

        // Build character description string for prompt
        $characterInstructions = '';
        $characters = $book->characters()->get();
        if ($characters->count() > 0) {
            $characterDescriptions = [];
            foreach ($characters as $character) {
                $desc = $character->name;
                if ($character->age) {
                    $desc .= " ({$character->age} years old)";
                }
                if ($character->gender) {
                    $desc .= ", {$character->gender}";
                }
                if ($character->description) {
                    $desc .= ": {$character->description}";
                }
                $characterDescriptions[] = $desc;
            }
            $characterInstructions = 'Characters: '.implode('; ', $characterDescriptions).'.';
        }

        // Generate image prompt using AI
        $systemPrompt = 'You are an expert at creating detailed image generation prompts for story illustrations. ';
        $systemPrompt .= 'Your prompts should be vivid, specific, and capture the essence of a scene. ';
        $systemPrompt .= 'CRITICAL: ONLY include characters who are PHYSICALLY PRESENT in the scene. ';
        $systemPrompt .= 'Do NOT include characters who are merely mentioned, remembered, thought about, or talked about but not bodily present. ';
        $systemPrompt .= 'A character must be at the physical location of the scene to appear in the image.';

        $userPrompt = "Based on this {$chapterLabel} content, create a single detailed image prompt for an illustration:\n\n";
        $userPrompt .= "Title: {$this->chapter->title}\n\n";

        if ($this->chapter->summary) {
            $userPrompt .= "Summary: {$this->chapter->summary}\n\n";
        }

        // Include a portion of the body for context
        $bodyPreview = substr($this->chapter->body ?? '', 0, 1000);
        if ($bodyPreview) {
            $userPrompt .= "Content Preview: {$bodyPreview}...\n\n";
        }

        if ($characterInstructions) {
            $userPrompt .= "{$characterInstructions}\n\n";
        }

        $userPrompt .= "Create a detailed one-sentence prompt for an image generation service describing a key scene from this {$chapterLabel}. ";
        $userPrompt .= 'Include visual details about setting, lighting, mood. ';
        $userPrompt .= 'CRITICAL: ONLY include characters who are PHYSICALLY PRESENT at the scene location - NOT characters who are mentioned, remembered, or talked about. ';
        $userPrompt .= 'The image should be in 16:7 landscape format.';

        $chatService->resetMessages();
        $chatService->addSystemMessage($systemPrompt);
        $chatService->addUserMessage($userPrompt);

        try {
            $result = $chatService->chat();
            $imagePrompt = trim($result['completion'] ?? '');

            if (empty($imagePrompt)) {
                Log::error('[GenerateChapterHeaderImageJob] Empty image prompt generated', [
                    'chapter_id' => $this->chapter->id,
                ]);

                $imageService->markError($imageRecord, 'Empty image prompt generated');
                event(new ImageGeneratedEvent($imageRecord->fresh()));

                return;
            }

            $strippedPrompt = $chapterBuilderService->stripQuotes($imagePrompt);

            Log::info('[GenerateChapterHeaderImageJob] Generated image prompt', [
                'chapter_id' => $this->chapter->id,
                'image_id' => $imageRecord->id,
                'prompt_preview' => substr($strippedPrompt, 0, 200),
            ]);

            // Update Image record with the prompt
            $imageService->updateById($imageRecord->id, ['prompt' => $strippedPrompt]);

            // Generate the image using FLUX 2 schema with character identification
            Log::info('[GenerateChapterHeaderImageJob] Starting image generation with FLUX 2 schema', [
                'chapter_id' => $this->chapter->id,
                'image_id' => $imageRecord->id,
            ]);

            $image = $chapterBuilderService->generateHeaderImage(
                $this->chapter->book_id,
                $this->chapter->id,
                $strippedPrompt
            );

            Log::info('[GenerateChapterHeaderImageJob] Image generation response received', [
                'chapter_id' => $this->chapter->id,
                'image_id' => $imageRecord->id,
                'has_image' => ! empty($image['image']),
                'image_url_preview' => isset($image['image']) ? substr((string) $image['image'], 0, 100) : 'null',
            ]);

            if (! empty($image['image'])) {
                // Update chapter to point to new image record
                $chapterService->updateById($this->chapter->id, [
                    'header_image_id' => $imageRecord->id,
                ], ['events' => false]);

                // Update Image record with the URL
                $imageService->markComplete($imageRecord, $image['image']);

                Log::info('[GenerateChapterHeaderImageJob] Completed successfully', [
                    'chapter_id' => $this->chapter->id,
                    'image_id' => $imageRecord->id,
                    'has_image' => true,
                ]);

                $this->chapter->refresh();
                ChapterUpdatedEvent::dispatch($this->chapter);
                event(new ImageGeneratedEvent($imageRecord->fresh()));

                Log::info('[GenerateChapterHeaderImageJob] Dispatched events', [
                    'chapter_id' => $this->chapter->id,
                    'book_id' => $this->chapter->book_id,
                    'image_id' => $imageRecord->id,
                ]);
            } else {
                $imageService->markError($imageRecord, 'Image generation returned empty result');
                event(new ImageGeneratedEvent($imageRecord->fresh()));

                Log::error('[GenerateChapterHeaderImageJob] Image generation failed', [
                    'chapter_id' => $this->chapter->id,
                    'image_id' => $imageRecord->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[GenerateChapterHeaderImageJob] Failed to generate image', [
                'chapter_id' => $this->chapter->id,
                'image_id' => $imageRecord->id,
                'error' => $e->getMessage(),
            ]);

            $imageService->markError($imageRecord, $e->getMessage());
            event(new ImageGeneratedEvent($imageRecord->fresh()));

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[GenerateChapterHeaderImageJob] Job failed', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'exception_class' => $exception ? get_class($exception) : 'unknown',
            'exception_message' => $exception?->getMessage() ?? 'unknown',
        ]);
    }
}
