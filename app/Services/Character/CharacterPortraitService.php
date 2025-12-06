<?php

namespace App\Services\Character;

use App\Events\Character\CharacterPortraitCreatedEvent;
use App\Models\Character;
use App\Services\Replicate\ReplicateApiService;
use App\Traits\Service\SavesImagesToS3;
use Illuminate\Support\Facades\Log;

class CharacterPortraitService
{
    use SavesImagesToS3;

    public function __construct(
        protected ReplicateApiService $replicateService
    ) {}

    /**
     * Generate and save a portrait for the given character.
     */
    public function generatePortrait(Character $character): bool
    {
        $character->load('book');

        if (! $character->book) {
            Log::warning("Character {$character->id} has no associated book, skipping portrait generation");

            return false;
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

            return false;
        }

        if ($result['url']) {
            $s3Path = $this->saveImageToS3($result['url'], 'portraits', $character->id);

            if ($s3Path) {
                $character->update(['portrait_image' => $s3Path]);

                event(new CharacterPortraitCreatedEvent($character));

                Log::info("Portrait generated and saved to S3 for character: {$character->name}", [
                    'character_id' => $character->id,
                    's3_path' => $s3Path,
                ]);

                return true;
            }

            Log::error("Failed to save portrait to S3 for character: {$character->name}", [
                'character_id' => $character->id,
                'remote_url' => $result['url'],
            ]);

            return false;
        }

        return false;
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

        return 'in a realistic photographic style. shot on Kodak Portra 400, natural grain, organic colors."';
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
            'erotica' => 'sexy, real',
            default => '',
        };
    }
}
