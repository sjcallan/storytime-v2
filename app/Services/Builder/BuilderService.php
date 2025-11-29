<?php

namespace App\Services\Builder;

use App\Services\Book\BookService;
use App\Services\Chapter\ChapterService;
use App\Services\Character\CharacterService;
use App\Services\OpenAi\ChatService;
use App\Services\OpenAi\DalleService;
use App\Services\StabilityAI\StabilityAIService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BuilderService
{
    /** @var \App\Services\OpenAi\ChatService */
    protected $chatService;

    /** @var \App\Services\Book\BookService */
    protected $bookService;

    /** @var \App\Services\OpenAi\DalleService */
    protected $dalleService;

    /** @var \App\Services\Chapter\ChapterService */
    protected $chapterService;

    /** @var \App\Services\Character\CharacterService */
    protected $characterService;

    protected $stabilityAIService;

    /**
     */
    public function __construct(ChatService $chatService, BookService $bookService, ChapterService $chapterService, DalleService $dalleService, StabilityAIService $stabilityAIService, CharacterService $characterService) 
    {
        $this->chatService = $chatService;
        $this->bookService = $bookService;
        $this->dalleService = $dalleService;
        $this->chapterService = $chapterService;
        $this->characterService = $characterService;
        $this->stabilityAIService = $stabilityAIService;
    }
    
    /**
     * @param string $bookId 
     * @param string $prompt
     */
    public function getImage(string $bookId, string $chapterId, string $prompt)
    {
        $book = $this->bookService->getById($bookId,  null, ['with' => 'chapters']);

        $style = '';

        if($book->age_level <= 12) {
            $style = 'in a cartoon style';
        }

        if($book->genre == 'Children') {
            $style .= ' in a bright cartoon style ';
        }

        if($book->genre == 'Science fiction') {
            $style .= ' in the style of star trek ';
        }

        if($book->genre == 'Romance') {
            $style .= ' fine art of, ';
        }

        if($book->genre == 'Horror') {
            $style .= ' gothic art of, ';
        }

        if($book->genre == 'Fantasy') {
            $style .= ' in the style of Neil Gaiman,';
        }

        $this->chatService->resetMessages();
        $this->chatService->addUserMessage('What happened?');
        $this->chatService->addAssistantMessage($prompt);
        
        $this->chatService->addUserMessage('In one sentence, what prompt should I give DALL-E to draw these characters in this scene. Exclude character names.');
        $imagePrompt = $this->chatService->chat();
        
        $this->chatService->trackRequestLog($bookId, $chapterId, $book->user_id, 'image_prompt', $imagePrompt);

        $prompt = $imagePrompt['completion'];
        $prompt = $this->stripQuotes($prompt);

        Log::debug('Image prompt: ' . $style . ' ' . $prompt);

        $imageResponse = $this->stabilityAIService->getImage($style . ' ' . $prompt, '16:9');

        if(!$imageResponse) {
            return [
                'image_prompt' => $style . ' ' . $prompt,
                'image' => null
            ];
        }

        $imageUrl = $imageResponse;
        
        return [
            'image_prompt' => $imagePrompt['completion'],
            'image' => $imageUrl
        ];
    }

    /**
     * 
     */
    public function getBodyWordCount(string $bookId): int
    {
        $book = $this->bookService->getById($bookId, ['type', 'age_level']);

        $length = 2000;

        if($book->age_level <=18) {
            $length = 2000;
        }

        if($book->age_level <=13) {
            $length = 1000;
        }

        if($book->age_level <=9) {
            $length = 800;
        }

        if($book->age_level <=4) {
            $length = 400;
        }

        if($book->age_level <=2) {
            $length = 200;
        }

        return $length;
    }

    /**
     * 
     */
    protected function getPersonaPrompt(string $bookId)
    {
        $book = $this->bookService->getById($bookId, ['id', 'plot', 'genre', 'age_level', 'user_characters', 'additional_instructions', 'scene', 'type']);

        $ageGroup = 'adult';

        if($book->age_level <=18) {
            $ageGroup = 'teenage';
        }

        if($book->age_level <=10) {
            $ageGroup = 'children';
        }

        if($book->age_level <=4) {
            $ageGroup = 'toddler';
        }
        
        return 'You are an author who is writing a fictitious ' . $ageGroup . ' ' . strtolower($book->genre) . '\'s ' . $book->type . ' book for ' . $book->age_level . ' year olds. ';
    }

    /**
     * 
     */
    protected function getSystemPrompt(string $bookId)
    {
        $book = $this->bookService->getById($bookId, ['id', 'plot', 'genre', 'age_level', 'user_characters', 'additional_instructions', 'scene', 'type']);

        $this->chatService->resetMessages();

        $prompt = $this->getPersonaPrompt($bookId);
        $prompt .=  'Be as descriptive as possible. ';

        $rules = [
            'The story is about ' . $book->plot,
            'Write in the third person',
            'The genre is ' . $book->genre
        ];

        if($book->type == 'chapter') {
            $rules[] = 'As a chapter book the story will not resolve until the end';
            $rules[] = 'Each chapter should end creating excitement for more ' . strtolower($book->genre) . ' in the next chapter';
        }

        if($book->type == 'screenplay') {
            $rules[] = 'As a screenplay the story will not resolve until the end';
            $rules[] = 'Lines should be written with the name of the character and their line.';
        }

        if($book->type == 'theatre') {
            $rules[] = 'As a theatre play the script will not resolve until the end.';
            $rules[] = 'Each line in the script should be written with the name of the character and their line.';
        }

        if($book->scene) {
            $rules[] = 'The scene is ' . $book->scene;
        }

        if($book->additional_instructions) {
            $rules[] = $book->additional_instructions;
        }

        if($book->user_characters) {
            $rules[] = 'The main characters are: ' . $book->user_characters;
        }

        $prompt .= 'Your rules are: ';

        foreach($rules AS $i => $rule) {
            $prompt .= 'Rule #' . $i . ': ' . $rule . ' |; ';
        }

        $prompt = rtrim($prompt, '; ');
        $prompt .= '. ';

        return $prompt;
    }

    /**
     * @param string $bookId 
     * @param array $data
     */
    protected function setBackstory(string $bookId)
    {
        $this->chatService->resetMessages();
        $this->chatService->setContext($this->getSystemPrompt($bookId));
    }

    /**
     * @param string $bookId
     */
    protected function soFar(string $bookId)
    {
        $book = $this->bookService->getById($bookId, ['id', 'plot', 'genre', 'age_level', 'user_characters', 'scene', 'type'], ['with' => ['chapters']]);

        if($book->chapters->where('status', 'complete')->count() > 1 ) {
            if($book->chapters->where('status', 'complete')->last()->book_summary) {
                $this->chatService->addUserMessage('What has happened so far?');
                $this->chatService->addAssistantMessage('So far ' . $book->chapters->where('status', 'complete')->last()->book_summary);
            }
        }
    }

    /**
     * @param string $bookId 
     * @param array $data
     */
    public function addCharacterProfiles(string $bookId)
    {
        if($characterPrompt = $this->getCharacterProfilePrompt($bookId)) {
            $this->chatService->addAssistantMessage('Who are the characters?');
            $this->chatService->addUserMessage($characterPrompt);
        }
    }

    /**
     * @param string $bookId
     */
    public function getCharacterProfilePrompt(string $bookId): string|null
    {
        $book = $this->bookService->getById($bookId, ['id', 'user_characters'], ['with' => ['chapters', 'chapters.characters', 'characters']]);
        $characterString = '';
        $lastChapter = $book->chapters->where('status', 'complete')->last();

        foreach($book->characters AS $character) {
            $characterString .= $character->name  . ': gender ' . $character->gender . ', age ' . $character->age . ', ' . $character->description;

            if($chapterCharacter = $lastChapter->characters->where('id', $character->id)->first()){
                if($chapterCharacter->pivot->experience) {
                    $characterString .= ' || has expererienced: ' . $chapterCharacter->pivot->experience;
                }

                if($chapterCharacter->pivot->inner_thoughts){
                    $characterString .= ' || is thinking about: ' . $chapterCharacter->pivot->inner_thoughts;
                }

                if($chapterCharacter->pivot->personal_motivations){
                    $characterString .= ' || is motivated by: ' . $chapterCharacter->pivot->personal_motivations;
                }

                if($chapterCharacter->pivot->goals){
                    $characterString .= ' || goals are: ' . $chapterCharacter->pivot->goals;
                }
            }

            $characterString .= '\n';
        }

        if($characterString == '') {
            $characterString = $book->user_characters;
        }

        return $characterString;
    }

    /**
     * 
     */
    public function lastChapter(string $bookId)
    {
        $book = $this->bookService->getById($bookId, ['id', 'plot', 'genre', 'age_level', 'user_characters', 'scene', 'type'], ['with' => ['chapters']]);
        if($book->chapters->where('status', 'complete')->count() > 0 ) {

            if($book->chapters->where('status', 'complete')->last()->summary) {
                $this->chatService->addUserMessage('What happened in the previous chapter?');
                $this->chatService->addAssistantMessage('In the previous chapter: ' . $book->chapters->where('status', 'complete')->last()->summary);

                $lastBody = $book->chapters->where('status', 'complete')->last()->body;
                $paragraphs = explode(PHP_EOL, $lastBody);
                $possibleParagraphs = [];
                foreach($paragraphs AS $paragraph) {
                    if($paragraph != '' && strlen($paragraph) > 20) {
                        $possibleParagraphs[] = $paragraph;
                    }
                }

                if(count($possibleParagraphs) >= 1) {
                    if(count($possibleParagraphs) >= 2) {
                        $lastParagraph = $possibleParagraphs[count($possibleParagraphs)-2] . ' ' . $possibleParagraphs[count($possibleParagraphs)-1];
                    }

                    $lastParagraph = $possibleParagraphs[count($possibleParagraphs)-1];

                    $this->chatService->addUserMessage('How did the last chapter end?');
                    $this->chatService->addAssistantMessage($lastParagraph);
                }
            }
        }
    }

    /**
     * @param string $msesage
     */
    public function stripQuotes(string $message = null):string|null
    {
        if(!$message) {
            return null;
        }

        if(str_starts_with($message, '"')) {
            $message = ltrim($message, '"');
        }

        if(str_ends_with($message, '"')) {
            $message = rtrim($message, '"');
        }

        return $message;
    }
}