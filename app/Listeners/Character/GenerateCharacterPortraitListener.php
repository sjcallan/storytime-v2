<?php

namespace App\Listeners\Character;

use App\Events\Character\CharacterPortraitCreatedEvent;
use App\Models\Character;
use App\Services\Replicate\ReplicateApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateCharacterPortraitListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected ReplicateApiService $replicateService) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $character = $event->character;

        if (! $character) {
            return;
        }

        $character->load('book');

        if (! $character->book) {
            Log::warning("Character {$character->id} has no associated book, skipping portrait generation");

            return;
        }

        $prompt = $this->buildPortraitPrompt($character);

        Log::info("Generating portrait for character: {$character->name}", [
            'character_id' => $character->id,
            'prompt' => $prompt,
        ]);

        $result = $this->replicateService->generateImage(
            prompt: $prompt,
            aspectRatio: '1:1'
        );

        if ($result['error']) {
            Log::error("Failed to generate portrait for character: {$character->name}", [
                'character_id' => $character->id,
                'error' => $result['error'],
            ]);

            return;
        }

        if ($result['url']) {
            $localPath = $this->saveImageLocally($result['url'], $character->id);

            if ($localPath) {
                $character->update(['portrait_image' => $localPath]);

                event(new CharacterPortraitCreatedEvent($character));

                Log::info("Portrait generated and saved locally for character: {$character->name}", [
                    'character_id' => $character->id,
                    'local_path' => $localPath,
                ]);
            } else {
                Log::error("Failed to save portrait locally for character: {$character->name}", [
                    'character_id' => $character->id,
                    'remote_url' => $result['url'],
                ]);
            }
        }
    }

    /**
     * Download and save the image to local storage.
     */
    protected function saveImageLocally(string $imageUrl, string $characterId): ?string
    {
        try {
            $response = Http::timeout(30)->get($imageUrl);

            if ($response->failed()) {
                Log::error('Failed to download portrait image', ['url' => $imageUrl]);

                return null;
            }

            $extension = 'webp';
            $filename = "portraits/{$characterId}_".Str::random(8).".{$extension}";

            Storage::disk('public')->put($filename, $response->body());

            return "/storage/{$filename}";
        } catch (\Exception $e) {
            Log::error('Error saving portrait image locally', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Build the portrait prompt based on character details and age-appropriate style.
     */
    protected function buildPortraitPrompt(Character $character): string
    {
        $style = $this->getAgeAppropriateStyle($character);
        $genreStyle = $this->getGenreStyle($character->book->genre ?? '');

        $promptParts = [
            'Portrait headshot',
            $style,
        ];

        if ($genreStyle) {
            $promptParts[] = $genreStyle;
        }

        $promptParts[] = 'of a character:';

        if ($character->name) {
            $promptParts[] = "named {$character->name},";
        }

        if ($character->age) {
            $promptParts[] = "{$character->age} years old,";
        }

        if ($character->gender) {
            $promptParts[] = "{$character->gender},";
        }

        if ($character->nationality) {
            $promptParts[] = "{$character->nationality} heritage,";
        }

        if ($character->description) {
            $promptParts[] = $character->description;
        }

        $promptParts[] = 'Centered composition, professional lighting, detailed face, expressive eyes, high quality';

        return implode(' ', $promptParts);
    }

    /**
     * Get the age-appropriate art style based on the book's target age level.
     * Uses cartoon style for kids and pre-teens, realism for teens and adults.
     */
    protected function getAgeAppropriateStyle(Character $character): string
    {
        $ageLevel = $character->book->age_level ?? 18;

        if ($ageLevel <= 4) {
            return 'in a bright, friendly cartoon style with soft rounded features';
        }

        if ($ageLevel <= 9) {
            return 'in a colorful cartoon style with expressive features';
        }

        if ($ageLevel <= 12) {
            return 'in a stylized cartoon style';
        }

        if ($ageLevel <= 15) {
            return 'in a semi-realistic illustration style';
        }

        return 'in a realistic photographic style';
    }

    /**
     * Get genre-specific style modifiers.
     */
    protected function getGenreStyle(?string $genre): string
    {
        if (! $genre) {
            return '';
        }

        return match (strtolower($genre)) {
            'children' => 'with warm, inviting colors',
            'science fiction' => 'in the style of Star Trek, futuristic',
            'romance' => 'fine art portrait, soft lighting',
            'horror' => 'gothic atmosphere, dramatic shadows',
            'fantasy' => 'in the style of Neil Gaiman, mystical',
            'adventure' => 'dynamic, adventurous mood',
            'mystery' => 'noir style, intriguing',
            default => '',
        };
    }
}
