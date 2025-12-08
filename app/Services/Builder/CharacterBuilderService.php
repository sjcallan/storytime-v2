<?php

namespace App\Services\Builder;

use App\Models\Book;
use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CharacterBuilderService extends BuilderService
{
    /** @var array */
    protected $bookCharacters;

    /**
     * Extract characters from a plot description using AI.
     *
     * @return array{characters: array<array{name: string, age: string, gender: string, description: string, backstory: string}>}
     */
    public function extractCharactersFromPlot(string $plot, string $genre = '', int $ageLevel = 10): array
    {
        Log::debug('Extracting characters from plot');

        $characterTemplate = [
            'characters' => [
                [
                    'name' => 'Character name',
                    'age' => 'Age as a number or description',
                    'gender' => 'male, female, or non-binary',
                    'description' => 'Physical appearance and personality traits',
                    'backstory' => 'Brief background story',
                ],
            ],
        ];

        $ageGroup = 'adult';
        if ($ageLevel <= 18) {
            $ageGroup = 'teenage';
        }
        if ($ageLevel <= 10) {
            $ageGroup = 'children';
        }
        if ($ageLevel <= 4) {
            $ageGroup = 'toddler';
        }

        $systemPrompt = "You are a creative writing assistant helping to create characters for a {$ageGroup} {$genre} story. ";
        $systemPrompt .= 'Based on the story plot provided, identify or create the main characters that would be in this story. ';
        $systemPrompt .= 'Create 2-4 interesting characters that fit the story. ';
        $systemPrompt .= 'Respond ONLY with valid JSON in this exact format: '.json_encode($characterTemplate);

        $this->chatService->resetMessages();
        $this->chatService->setModel('gpt-4o-mini');
        $this->chatService->setTemperature(0.7);
        $this->chatService->setResponseFormat('json_object');
        $this->chatService->setContext($systemPrompt);
        $this->chatService->addUserMessage("Here is the story plot:\n\n{$plot}\n\nCreate the characters for this story.");

        $response = $this->chatService->chat();

        if (isset($response['error']) || empty($response['completion'])) {
            Log::error('Failed to extract characters from plot', ['error' => $response['error'] ?? 'No completion']);

            return ['characters' => []];
        }

        $completion = $response['completion'];
        $parsed = json_decode($this->cleanupResponse($completion), true);

        if (! $parsed || ! isset($parsed['characters'])) {
            Log::error('Failed to parse character response', ['completion' => $completion]);

            return ['characters' => []];
        }

        return $parsed;
    }

    /**
     * @param  int  $characterId
     * @param  array  $data
     */
    public function getChapterCharacters(string $chapterId, string $summary): string
    {
        Log::debug('CHAPTER CHARACTERS');
        $chapter = $this->chapterService->getById($chapterId);

        $characterTemplate = [
            'characters' => [
                [
                    'name' => '',
                    'description' => '',
                    'gender' => '',
                    'age' => '',
                    'nationality' => '',
                    'thoughts' => '',
                    'motivations' => '',
                    'goals' => '',
                    'experience' => '',
                ],
            ],
        ];

        $this->chatService->setContext('About: '.$this->getPersonaPrompt($chapter->book_id).' Summary: '.$summary.' Respond using the JSON Format. "'.json_encode($characterTemplate).'"');
        $this->chatService->setTemperature(.25);
        $this->chatService->setResponseFormat('json_object');
        $this->chatService->addUserMessage($this->getCharacterPrompt());

        $characterResponse = $this->chatService->chat();
        $characters = $characterResponse['completion'];

        $this->chatService->trackRequestLog($chapter->book_id, $chapter->id, $chapter->user_id, 'chapter_characters', $characterResponse);

        return $characters;
    }

    /**
     * @param  int  $characterId
     * @param  array  $data
     */
    public function getBookCharacters(string $bookId, string $userCharacters): string
    {
        Log::debug('CHAPTER CHARACTERS');
        $book = $this->bookService->getById($bookId, ['user_id', 'id']);

        $characterTemplate = [
            'characters' => [
                [
                    'name' => '',
                    'description' => '',
                    'gender' => '',
                    'age' => '',
                    'nationality' => '',
                    'thoughts' => '',
                    'motivations' => '',
                    'goals' => '',
                    'experience' => '',
                ],
            ],
        ];

        $this->chatService->setContext($this->getPersonaPrompt($bookId).' Imagine no more than 4 primary characters based on the plot of this book, respond using json format: "'.json_encode($characterTemplate).'"');
        $this->chatService->setModel('gpt-4.1');
        $this->chatService->setResponseFormat('json_object');
        $this->chatService->addUserMessage('Who are the characters?');
        $this->chatService->addAssistantMessage($userCharacters);
        $this->chatService->addUserMessage($this->getCharacterPrompt());

        $characterResponse = $this->chatService->chat();
        $characters = $characterResponse['completion'];

        $this->chatService->trackRequestLog($bookId, 0, $book->user_id, 'book_characters', $characterResponse);

        return $characters;
    }

    protected function getCharacterPrompt(): string
    {
        $message = 'Who are the characters? Create a valid json array, one object per character in this chapter each formatted like: {name:"",description:"",gender:"",age:"",nationality:"",thoughts:"",motivations:"",goals:"",experience:""}'
            .' replace name with the character\'s name.'
            .' for description: a physical description of this character'
            .' for gender: the sex of this character, choose: male or female'
            .' for age: the character\'s age in years, do not say unknown'
            .' for nationality: choose the character\'s country of origin or accent, do not say unknown'
            .' for goals: What is this character trying to accomplish in this chapter?'
            .' for thoughts: What is this character thinking about at the moment?'
            .' for motivations: What is this character\'s personal motivations?'
            .' for experience: Summarize what this character experienced in this chaper.';

        return $message;
    }

    public function saveCharacterResponse(string $chatResponse, string $bookId, string $chapterId = ''): void
    {
        Log::debug('Save characters: ');
        Log::debug($chatResponse);

        if (is_array($chatResponse) || is_object($chatResponse)) {
            $responseCharacters = $chatResponse->characters;
        } else {
            if (! $responseCharacters = json_decode($this->cleanupResponse($chatResponse), true)) {
                Log::debug('bad json');
            }

            $responseCharacters = $responseCharacters['characters'];
        }

        $book = $this->bookService->getById($bookId, null, ['with' => ['characters']]);
        $this->bookCharacters = $book->characters->pluck('id', 'name')->toArray();

        foreach ($responseCharacters as $responseCharacter) {
            $character = $this->getOrCreateCharacter($responseCharacter, $book);

            DB::table('chapter_character')->insert([
                'book_id' => $bookId,
                'chapter_id' => $chapterId,
                'character_id' => $character->id,
                'user_id' => $book->user_id,
                'goals' => $responseCharacter['goals'],
                'inner_thoughts' => $responseCharacter['thoughts'],
                'personal_motivations' => $responseCharacter['motivations'],
                'experience' => $responseCharacter['experience'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    protected function cleanupResponse(string $text): string
    {
        $text = trim($text);
        $text = ltrim($text, '`');
        $text = rtrim($text, '`');
        $text = trim(preg_replace('/\s\s+/', ' ', $text));

        return $text;
    }

    /**
     * @param  \App\Models\Chapter  $chapter
     */
    protected function getOrCreateCharacter(array $responseCharacter, Book $book): Character
    {
        $characterName = strtolower(trim($responseCharacter['name']));
        Log::debug('Compare '.$characterName.' to '.json_encode($this->bookCharacters));

        $character = null;

        foreach ($this->bookCharacters as $name => $id) {
            if (strtolower(trim($characterName)) == strtolower(trim($name))) {
                return $this->characterService->getById($this->bookCharacters[$responseCharacter['name']]);
            }
        }

        $character = $this->characterService->store([
            'book_id' => $book->id,
            'user_id' => $book->user_id,
            'type' => 'book',
            'name' => $responseCharacter['name'],
            'description' => $responseCharacter['description'],
            'gender' => $responseCharacter['gender'],
            'age' => $responseCharacter['age'],
            'backstory' => '',
            'nationality' => $responseCharacter['nationality'],
        ]);

        $this->bookCharacters[$responseCharacter['name']] = $character->id;

        return $character;
    }
}
