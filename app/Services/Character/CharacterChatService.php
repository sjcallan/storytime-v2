<?php

namespace App\Services\Character;

use App\Services\Conversation\ConversationMessageService;
use App\Services\Conversation\ConversationService;
use App\Services\OpenAi\ChatService;
use Illuminate\Support\Facades\Auth;

class CharacterChatService
{
    protected $user;

    protected $thread = [];

    protected $character;

    protected $e = false;

    protected $conversation;

    public function __construct(protected CharacterService $characterService, protected ChatService $chatService, protected ConversationService $conversationService, protected ConversationMessageService $conversationMessageService) {}

    public function startConversation(int $characterId)
    {
        $this->user = Auth::user();
        $this->character = $this->characterService->getById($characterId, null, ['with' => ['chapters']]);

        if (! $conversation = $this->conversationService->getByUserCharacterId($this->user->id, $this->character->id, null, ['with' => ['messages', 'user']])) {
            $conversation = $this->conversationService->store([
                'user_id' => $this->user->id,
                'type' => $this->character->type,
                'character_id' => $this->character->id,
                'character_name' => $this->character->name,
                'character_gender' => $this->character->gender,
                'character_age' => $this->character->age,
                'character_nationality' => $this->character->nationality,
            ]);
        }

        $this->conversation = $conversation;

        return $conversation;
    }

    public function getResponse(string $question): object
    {

        switch ($this->character->type) {
            case 'figure':
                $this->addFigureContext();
                break;

            case 'fictional':
                $this->addPersonaContext();
                break;

            default:
                $this->addContext();
                break;
        }
        $this->addThread();

        if (str_starts_with($question, 'e:')) {
            $this->e = true;
            $question = rtrim('e:'.$question);
        }

        $fullQuestion = $this->user->name.' says to '.$this->character->name.' "'.$question.'". '.$this->character->name.' responds by saying to '.$this->user->name;
        $this->chatService->setTemperature(1);
        $this->chatService->addUserMessage($fullQuestion);

        if (! $response = $this->chatService->chat()) {
            $response = 'error: '.$this->chatService->getError();
        } else {
            $response = $this->stripQuotes($response['completion']);
        }

        $message = $this->conversationMessageService->store([
            'conversation_id' => $this->conversation->id,
            'character_id' => $this->character->id,
            'message' => $question,
            'response' => $response,
        ]);

        return $message;
    }

    /**
     * @param  string  $msesage
     */
    protected function stripQuotes(?string $message = null): ?string
    {
        if (! $message) {
            return null;
        }

        if (str_starts_with($message, '"')) {
            $message = ltrim($message, '"');
        }

        if (str_ends_with($message, '"')) {
            $message = rtrim($message, '"');
        }

        return $message;
    }

    protected function cleanupJsonResponse(string $text): string
    {
        $text = trim($text);
        $text = ltrim($text, '`');
        $text = rtrim($text, '`');
        $text = trim(preg_replace('/\s\s+/', ' ', $text));

        return $text;
    }

    public function getFigureAge()
    {
        $this->chatService->addUserMessage('What age is the figure '.$this->character->name.'  most often portrayed as? Respond only with a number.');

        $response = $this->chatService->chat();
        $response = $this->stripQuotes($response['completion']);

        return $response;
    }

    protected function addContext(): void
    {
        $context = [
            'You are role-playing as '.$this->character->name.' from a '.strtolower($this->character->book->genre).' novel for '.$this->character->book->age_level.' year olds. Here is some of their background details: ',
        ];

        if ($this->character->description) {
            $context[] = ' Your Description: '.$this->character->description;
        }

        if ($this->character->age) {
            $context[] = ' Your Age: '.$this->character->age;
        }

        if ($this->character->age) {
            $context[] = ' Your Gender: '.$this->character->gender;
        }

        if ($this->character->backstory) {
            $context[] = ' Your Backstory: '.$this->character->backstory;
        }

        $chapterExperiences = [];
        foreach ($this->character->chapters as $i => $chapter) {
            $message = 'Your memory #'.$i.': ';

            if ($chapter->pivot->experience) {
                $message .= $chapter->pivot->experience;
            }

            if ($chapter->pivot->inner_thoughts) {
                $message .= '### your inner thoughts were '.$chapter->pivot->inner_thoughts;
            }

            if ($chapter->pivot->goals) {
                $message .= '### your goal was '.$chapter->pivot->goals;
            }

            $chapterExperiences[] = $message;
        }

        if ($chapterExperiences) {
            $chapterExperiences[0] = 'Memories: '.$chapterExperiences[0];
        }

        $context = array_merge($context, $chapterExperiences);
        $context[] = 'Answer questions as '.$this->character->name.' would, short and authentic to their personality and way of speaking. ### respond with only '.$this->character->name.'\'s spoken words.### Respond to me by name.';

        $this->chatService->resetMessages();
        $this->chatService->setContext(implode('### ', $context));
    }

    protected function addFigureContext()
    {
        if ($this->e == true) {
            $leadIn = 'You are an author writing an fictional '.config('app.genre_test').' novel for adults. The story is about a person named '.$this->character->name;
        } else {
            $leadIn = 'You are an actor portraying a character by the name of'.$this->character->name;
        }

        $context = [
            $leadIn,
            'Answer questions as '.$this->character->name.' would.',
            'Respond with only '.$this->character->name.'\'s spoken words.',
        ];

        $this->chatService->resetMessages();
        $this->chatService->setContext(implode('### ', $context));
    }

    protected function addPersonaContext()
    {
        $prompt = 'You are an actor portraying a character by the name of'.$this->character->name;

        $context = [
            $prompt,
            $this->character->name.' is a '.$this->character->age.' year old, gender is '.$this->character->gender,
            $this->character->name.'\s is described as '.$this->character->description,
            $this->character->name.'\s backstory is '.$this->character->backstory,
            'Answer questions as '.$this->character->name.' would.',
            'Respond with only '.$this->character->name.'\'s spoken words.',
        ];

        $this->chatService->resetMessages();
        $this->chatService->setContext(implode('### ', $context));
    }

    protected function addThread()
    {
        $this->chatService->addUserMessage('Hi '.$this->character->name.', my name is '.$this->user->name.'.');
        $this->chatService->addAssistantMessage('It is nice to meet you '.$this->user->name.', my name is '.$this->character->name.'.');

        foreach ($this->conversation->messages as $message) {
            $this->chatService->addUserMessage($message->message);
            $this->chatService->addAssistantMessage($message->response);
        }
    }

    public function getFigureDetails(string $figure): array
    {
        $prompt = 'Create a JSON response formatted as {age:"",gender:"",nationality:"",nationality:""} ### '
        .'In the age property: In real life, what gender is the figure '.$figure.'? response with the word "male" or "female" ### '
        .'In the age property: What age is '.$figure.'? If they are deceased, respond with age number they are most often portrayed as. If alive, respond with the current age number. ### '
        .'In the nationality property: Where is '.$figure.' from?';

        $this->chatService->addUserMessage($prompt);
        $this->chatService->setTemperature(.25);
        $response = $this->chatService->chat();

        $response = $this->cleanupJsonResponse($response['completion']);

        return json_decode($response, true);
    }
}
