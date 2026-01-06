<?php

namespace App\Jobs\Chapter;

use App\Events\Character\CharacterCreatedEvent;
use App\Models\Chapter;
use App\Services\Ai\OpenAi\ChatService;
use App\Services\Character\CharacterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExtractChapterCharactersJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Chapter $chapter
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ChatService $chatService, CharacterService $characterService): void
    {
        Log::info('[ExtractChapterCharactersJob] Starting character extraction', [
            'chapter_id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
        ]);

        try {
            $this->chapter->load(['book.characters']);
            $book = $this->chapter->book;

            if (! $book) {
                Log::warning('[ExtractChapterCharactersJob] No book found for chapter', [
                    'chapter_id' => $this->chapter->id,
                ]);

                return;
            }

            if (empty($this->chapter->body)) {
                Log::warning('[ExtractChapterCharactersJob] Chapter has no body content', [
                    'chapter_id' => $this->chapter->id,
                ]);

                return;
            }

            $existingCharacters = $book->characters;
            $existingCharacterNames = $existingCharacters->pluck('name')->map(fn ($name) => strtolower(trim($name)))->toArray();

            Log::debug('[ExtractChapterCharactersJob] Existing characters', [
                'chapter_id' => $this->chapter->id,
                'existing_count' => count($existingCharacterNames),
                'existing_names' => $existingCharacterNames,
            ]);

            $newCharacters = $this->extractNewCharactersFromChapter(
                $chatService,
                $this->chapter->body,
                $this->chapter->summary ?? '',
                $existingCharacterNames,
                $book->genre ?? '',
                $book->age_level ?? 10
            );

            if (empty($newCharacters)) {
                Log::info('[ExtractChapterCharactersJob] No new characters found in chapter', [
                    'chapter_id' => $this->chapter->id,
                ]);

                return;
            }

            Log::info('[ExtractChapterCharactersJob] New characters identified', [
                'chapter_id' => $this->chapter->id,
                'new_character_count' => count($newCharacters),
                'new_character_names' => array_column($newCharacters, 'name'),
            ]);

            $createdCharacters = [];

            foreach ($newCharacters as $characterData) {
                $characterName = strtolower(trim($characterData['name'] ?? ''));

                if (empty($characterName)) {
                    continue;
                }

                if (in_array($characterName, $existingCharacterNames)) {
                    Log::debug('[ExtractChapterCharactersJob] Skipping duplicate character', [
                        'name' => $characterData['name'],
                    ]);

                    continue;
                }

                $character = $characterService->store([
                    'book_id' => $book->id,
                    'user_id' => $book->user_id,
                    'type' => 'book',
                    'name' => $characterData['name'],
                    'description' => $characterData['description'] ?? '',
                    'gender' => $characterData['gender'] ?? '',
                    'age' => $characterData['age'] ?? '',
                    'backstory' => $characterData['backstory'] ?? '',
                    'nationality' => $characterData['nationality'] ?? '',
                ], ['events' => false]);

                $existingCharacterNames[] = $characterName;
                $createdCharacters[] = $character;

                Log::info('[ExtractChapterCharactersJob] Character created', [
                    'chapter_id' => $this->chapter->id,
                    'character_id' => $character->id,
                    'character_name' => $character->name,
                ]);

                event(new CharacterCreatedEvent($character));
            }

            Log::info('[ExtractChapterCharactersJob] Character extraction completed', [
                'chapter_id' => $this->chapter->id,
                'characters_created' => count($createdCharacters),
            ]);
        } catch (Throwable $e) {
            Log::error('[ExtractChapterCharactersJob] Exception during character extraction', [
                'chapter_id' => $this->chapter->id,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Use AI to extract new characters from the chapter content.
     *
     * @param  array<string>  $existingCharacterNames
     * @return array<array{name: string, age: string, gender: string, description: string, backstory: string, nationality: string}>
     */
    protected function extractNewCharactersFromChapter(
        ChatService $chatService,
        string $chapterBody,
        string $chapterSummary,
        array $existingCharacterNames,
        string $genre,
        int $ageLevel
    ): array {
        $characterTemplate = [
            'new_characters' => [
                [
                    'name' => 'Character name',
                    'age' => 'Age as a number or estimate',
                    'gender' => 'male, female, or non-binary',
                    'description' => 'Physical appearance and personality traits',
                    'backstory' => 'Brief background if mentioned or implied',
                    'nationality' => 'Country of origin or ethnicity if mentioned',
                ],
            ],
        ];

        $existingList = empty($existingCharacterNames)
            ? 'No existing characters yet.'
            : 'Existing characters (do NOT include these): '.implode(', ', $existingCharacterNames);

        $systemPrompt = "You are analyzing a chapter from a {$genre} story written for {$ageLevel} year old readers. ";
        $systemPrompt .= 'Your task is to identify any NEW named characters that appear in this chapter who are not in the existing character list. ';
        $systemPrompt .= 'Only include characters who have names and play a meaningful role (not just mentioned in passing). ';
        $systemPrompt .= 'Do NOT include existing characters. Do NOT include unnamed characters like "the guard" or "a merchant". ';
        $systemPrompt .= 'If there are no new named characters, return an empty array. ';
        $systemPrompt .= 'Respond ONLY with valid JSON in this exact format: '.json_encode($characterTemplate);

        $userPrompt = "{$existingList}\n\n";
        $userPrompt .= "Chapter content:\n\n{$chapterBody}";

        if (! empty($chapterSummary)) {
            $userPrompt .= "\n\nChapter summary:\n{$chapterSummary}";
        }

        $userPrompt .= "\n\nIdentify any NEW named characters in this chapter that are not in the existing character list.";

        $chatService->resetMessages();
        $chatService->setTemperature(0.3);
        $chatService->setMaxTokens(2000);
        $chatService->setResponseFormat('json_object');
        $chatService->addSystemMessage($systemPrompt);
        $chatService->addUserMessage($userPrompt);

        $response = $chatService->chat();

        if (isset($response['error']) || empty($response['completion'])) {
            Log::error('[ExtractChapterCharactersJob] AI request failed', [
                'error' => $response['error'] ?? 'No completion returned',
            ]);

            return [];
        }

        $completion = $response['completion'];
        $parsed = json_decode($this->cleanupResponse($completion), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[ExtractChapterCharactersJob] Failed to parse AI response', [
                'json_error' => json_last_error_msg(),
                'completion' => $completion,
            ]);

            return [];
        }

        if (! isset($parsed['new_characters']) || ! is_array($parsed['new_characters'])) {
            Log::debug('[ExtractChapterCharactersJob] No new_characters array in response', [
                'parsed' => $parsed,
            ]);

            return [];
        }

        return $parsed['new_characters'];
    }

    /**
     * Clean up AI response text for JSON parsing.
     */
    protected function cleanupResponse(string $text): string
    {
        $text = trim($text);
        $text = ltrim($text, '`');
        $text = rtrim($text, '`');

        if (str_starts_with($text, 'json')) {
            $text = substr($text, 4);
        }

        return trim($text);
    }
}
