<?php

namespace App\Services\Builder;

use Illuminate\Support\Facades\Log;

class BookBuilderService extends BuilderService
{
    /**
     * @param  array  $data
     */
    public function getBookTitle(string $bookId)
    {
        Log::debug('BOOK TITLE');

        $book = $this->bookService->getById($bookId, ['genre', 'user_id']);

        switch ($book->genre) {
            case 'childrens':
                $prompt = 'Write a heartwarming title for this book in less than 6 words.';
                break;

            case 'romance':
                $prompt = 'Write a romantic title for this book in less than 6 words.';
                break;

            case 'paranormal':
                $prompt = 'Write an eerie title for this book in less than 6 words.';
                break;

            case 'adventure':
                $prompt = 'Write a thrilling title for this book in less than 6 words.';
                break;

            default:
                $prompt = 'Write a compelling title for this book in less than 6 words.';
                break;
        }

        $prompt .= ' Do not use colons or punctuation.';

        $this->setBackstory($bookId);
        $this->chatService->addUserMessage($prompt);

        $titleResponse = $this->chatService->chat();
        $title = $titleResponse['completion'];
        $title = $this->stripQuotes($title);

        $this->chatService->trackRequestLog($bookId, 0, $book->user_id, 'book_title', $titleResponse);

        return $title;
    }

    public function getBookMetaData(string $bookId, string $userCharacters = ''): array
    {
        Log::debug('BOOK METADATA');

        $book = $this->bookService->getById($bookId, ['genre', 'user_id']);

        $titlePrompt = match ($book->genre) {
            'childrens' => 'Write a heartwarming title for this book in less than 6 words.',
            'romance' => 'Write a romantic title for this book in less than 6 words.',
            'paranormal' => 'Write an eerie title for this book in less than 6 words.',
            'adventure' => 'Write a thrilling title for this book in less than 6 words.',
            default => 'Write a compelling title for this book in less than 6 words.',
        };

        $titlePrompt .= ' Do not use colons or punctuation.';

        $responseTemplate = [
            'title' => '',
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

        $characterPrompt = 'Who are all the characters? Create a valid json array, one object per character each formatted like: {name:"",description:"",gender:"",age:"",nationality:"",thoughts:"",motivations:"",goals:"",experience:""}'
            .' replace name with the character\'s name.'
            .' for description: a physical description of this character'
            .' for gender: the sex of this character, choose: male or female'
            .' for age: the character\'s age in years, do not say unknown'
            .' for nationality: choose the character\'s country of origin or accent, do not say unknown'
            .' for goals: What is this character trying to accomplish in this book?'
            .' for thoughts: What is this character thinking about at the moment?'
            .' for motivations: What is this character\'s personal motivations?'
            .' for experience: Summarize what this character experienced in this book.';

        $this->chatService->setContext($this->getPersonaPrompt($bookId).' Respond using the JSON Format: "'.json_encode($responseTemplate).'"');
        $this->chatService->setTemperature(.25);
        $this->chatService->setResponseFormat('json_object');

        if ($userCharacters) {
            $this->chatService->addUserMessage('Who are the characters?');
            $this->chatService->addAssistantMessage($userCharacters);
        }

        $this->chatService->addUserMessage($titlePrompt.' ALSO: '.$characterPrompt);

        $metaDataResponse = $this->chatService->chat();
        $metaData = json_decode($metaDataResponse['completion'], true);

        $this->chatService->trackRequestLog($bookId, 0, $book->user_id, 'book_metadata', $metaDataResponse);

        return [
            'title' => $this->stripQuotes($metaData['title'] ?? ''),
            'characters' => $metaData['characters'] ?? [],
            'user_id' => $book->user_id,
        ];
    }
}
