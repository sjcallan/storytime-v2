<?php

namespace App\Services\Character;

use App\Events\Character\AllCharactersPortraitsCreatedEvent;
use App\Events\Character\CharacterPortraitCreatedEvent;
use App\Models\Character;
use App\Services\Ai\OpenAi\ChatService;
use App\Services\Replicate\ReplicateApiService;
use App\Traits\Service\SavesImagesToS3;
use Illuminate\Support\Facades\Log;

class CharacterPortraitService
{
    use SavesImagesToS3;

    public function __construct(
        protected ReplicateApiService $replicateService,
        protected ChatService $chatService
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
        $ageLevel = $character->book->age_level ?? 18;
        $useKreaModel = $ageLevel > 15;

        $character->update(['portrait_image_prompt' => $prompt]);

        Log::info("Generating portrait for character: {$character->name}", [
            'character_id' => $character->id,
            'prompt' => $prompt,
            'age_level' => $ageLevel,
            'using_krea_model' => $useKreaModel,
        ]);

        $trackingContext = [
            'item_type' => 'character_portrait',
            'user_id' => $character->book->user_id,
            'profile_id' => $character->book->profile_id,
            'book_id' => $character->book_id,
            'character_id' => $character->id,
        ];

        $result = $useKreaModel
            ? $this->replicateService->generateImageWithKrea(
                prompt: $prompt,
                aspectRatio: '1:1',
                trackingContext: $trackingContext
            )
            : $this->replicateService->generateImage(
                prompt: $prompt,
                inputImages: null,
                aspectRatio: '1:1',
                trackingContext: $trackingContext
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

                // Check if all characters have a portrait
                $totalCharacters = Character::where('book_id', $character->book_id)->count();
                $charactersWithPortrait = Character::where('book_id', $character->book_id)
                    ->whereNotNull('portrait_image')
                    ->count();

                $allCharactersHavePortrait = ($charactersWithPortrait === $totalCharacters);

                Log::info('Checking if all characters have portraits', [
                    'book_id' => $character->book_id,
                    'total_characters' => $totalCharacters,
                    'characters_with_portrait' => $charactersWithPortrait,
                    'all_have_portraits' => $allCharactersHavePortrait,
                ]);

                if ($allCharactersHavePortrait) {
                    Log::info('All characters have portraits - firing event', [
                        'book_id' => $character->book_id,
                    ]);
                    event(new AllCharactersPortraitsCreatedEvent($character->book));
                }

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
     * Build the portrait prompt using AI based on character details and age-appropriate style.
     */
    protected function buildPortraitPrompt(Character $character): string
    {
        $ageLevel = $character->book->age_level ?? 18;
        $genre = $character->book->genre ?? 'general fiction';

        $characterDetails = $this->gatherCharacterDetails($character);
        $styleGuidance = $this->getStyleGuidance($ageLevel, $genre);

        $systemPrompt = $this->buildSystemPrompt($styleGuidance, $ageLevel);
        $userPrompt = $this->buildUserPrompt($characterDetails, $ageLevel, $genre);

        Log::info('Generating AI prompt for character portrait', [
            'character_id' => $character->id,
            'age_level' => $ageLevel,
            'genre' => $genre,
        ]);

        $this->chatService->resetMessages();
        $this->chatService->setMaxTokens(500);
        $this->chatService->setTemperature(0.5);

        $this->chatService->addSystemMessage($systemPrompt);
        $this->chatService->addUserMessage($userPrompt);

        $response = $this->chatService->chat();

        if (isset($response['error']) || empty($response['completion'])) {
            Log::error('Failed to generate AI prompt for character portrait', [
                'character_id' => $character->id,
                'error' => $response['error'] ?? 'Empty completion',
            ]);

            return $this->buildFallbackPrompt($character);
        }

        $generatedPrompt = trim($response['completion']);

        Log::info('AI prompt generated successfully', [
            'character_id' => $character->id,
            'prompt_length' => strlen($generatedPrompt),
            'tokens_used' => $response['total_tokens'] ?? 0,
        ]);

        return $generatedPrompt;
    }

    /**
     * Gather character details into an array.
     *
     * @return array<string, mixed>
     */
    protected function gatherCharacterDetails(Character $character): array
    {
        return [
            'name' => $character->name,
            'age' => $character->age,
            'gender' => $character->gender,
            'nationality' => $character->nationality,
            'description' => $character->description,
        ];
    }

    /**
     * Get style guidance based on age level and genre.
     */
    protected function getStyleGuidance(int $ageLevel, string $genre): string
    {
        $ageStyle = $this->getAgeAppropriateStyleDescription($ageLevel);
        $genreStyle = $this->getGenreStyleDescription($genre);

        $guidance = "Art Style: {$ageStyle}";

        if ($genreStyle) {
            $guidance .= "\nGenre Style: {$genreStyle}";
        }

        return $guidance;
    }

    /**
     * Get the age-appropriate art style description.
     */
    protected function getAgeAppropriateStyleDescription(int $ageLevel): string
    {
        if ($ageLevel <= 4) {
            return 'Bright, friendly cartoon style with soft rounded features, suitable for toddlers';
        }

        if ($ageLevel <= 9) {
            return 'Colorful cartoon style with expressive features, suitable for young children';
        }

        if ($ageLevel <= 12) {
            return 'Stylized cartoon/illustration style, suitable for pre-teens';
        }

        if ($ageLevel <= 15) {
            return 'Semi-realistic illustration style with some stylization, suitable for teens';
        }

        return 'Realistic photographic style with natural lighting and film-like quality, photographed with professional camera equipment like Nikon Z8 with 85mm f/1.8 lens for authentic depth of field';
    }

    /**
     * Get genre-specific style description.
     */
    protected function getGenreStyleDescription(string $genre): string
    {
        return match (strtolower($genre)) {
            'children' => 'Warm, inviting colors that are comforting and friendly',
            'science fiction' => 'Futuristic aesthetic inspired by classic sci-fi like Star Trek, with clean lines and technological elements',
            'romance' => 'Fine art portrait style with soft, flattering lighting and romantic atmosphere',
            'horror' => 'Gothic atmosphere with dramatic shadows and slightly unsettling mood',
            'fantasy' => 'Mystical and imaginative, inspired by authors like Neil Gaiman',
            'adventure' => 'Dynamic composition with adventurous, energetic mood',
            'mystery' => 'Film noir inspired with moody lighting and intriguing atmosphere',
            'erotica' => 'Sophisticated and sensual with artistic maturity',
            default => '',
        };
    }

    /**
     * Build the system prompt for AI.
     */
    protected function buildSystemPrompt(string $styleGuidance, int $ageLevel): string
    {
        $modelType = $ageLevel > 15 ? 'flux-krea-dev (realistic photographic model)' : 'flux-2-pro (versatile illustration model)';

        return "You are an expert at writing prompts for AI image generation models. Your task is to write a single, detailed description ofa character portrait headshot.
{$styleGuidance}

Write a clear, detailed description of the portrait headshot. Focus on:
- Physical appearance and facial features
- Expression and emotion
- Lighting and composition appropriate to the style
- Technical photography details (if realistic style), including camera type and lens choice, aperture, ISO, shutter speed, and focal length.
- Age-appropriate styling

Be specific and descriptive. Write only the description itself, no explanations or meta-commentary.";
    }

    /**
     * Build the user prompt with character details.
     *
     * @param  array<string, mixed>  $characterDetails
     */
    protected function buildUserPrompt(array $characterDetails, int $ageLevel, string $genre): string
    {
        $prompt = "Create an image generation prompt for a portrait headshot of this character:\n\n";

        if ($characterDetails['name']) {
            $prompt .= "Name: {$characterDetails['name']}\n";
        }

        if ($characterDetails['age']) {
            $prompt .= "Age: {$characterDetails['age']} years old\n";
        }

        if ($characterDetails['gender']) {
            $prompt .= "Gender: {$characterDetails['gender']}\n";
        }

        if ($characterDetails['nationality']) {
            $prompt .= "Heritage: {$characterDetails['nationality']}\n";
        }

        if ($characterDetails['description']) {
            $prompt .= "Description: {$characterDetails['description']}\n";
        }

        $prompt .= "\nBook Genre: {$genre}\n";
        $prompt .= "Target Age Level: {$ageLevel}";

        return $prompt;
    }

    /**
     * Build a fallback prompt if AI generation fails.
     */
    protected function buildFallbackPrompt(Character $character): string
    {
        $ageLevel = $character->book->age_level ?? 18;
        $style = $this->getAgeAppropriateStyleDescription($ageLevel);

        $parts = [
            'Portrait headshot',
            $style,
            'of a character',
        ];

        if ($character->name) {
            $parts[] = "named {$character->name}";
        }

        if ($character->age) {
            $parts[] = "{$character->age} years old";
        }

        if ($character->gender) {
            $parts[] = $character->gender;
        }

        if ($character->nationality) {
            $parts[] = "{$character->nationality} heritage";
        }

        if ($character->description) {
            $parts[] = $character->description;
        }

        return implode(', ', $parts);
    }
}
