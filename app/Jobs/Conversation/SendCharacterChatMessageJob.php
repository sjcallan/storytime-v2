<?php

namespace App\Jobs\Conversation;

use App\Events\ConversationMessage\CharacterChatResponseEvent;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Ai\AiManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCharacterChatMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function __construct(
        public ConversationMessage $conversationMessage,
        public array $conversationHistory = []
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AiManager $aiManager): void
    {
        $conversation = $this->conversationMessage->conversation;
        $character = $conversation->character;

        if (! $character) {
            $this->conversationMessage->update([
                'response' => 'Sorry, I could not find the character to chat with.',
            ]);
            event(new CharacterChatResponseEvent($this->conversationMessage->fresh()));

            return;
        }

        $chatService = $aiManager->chat();
        $chatService->resetMessages();

        $systemPrompt = $this->buildSystemPrompt($character, $conversation);
        $chatService->setContext($systemPrompt);

        foreach ($this->conversationHistory as $historyMessage) {
            if ($historyMessage['role'] === 'user') {
                $chatService->addUserMessage($historyMessage['content']);
            } elseif ($historyMessage['role'] === 'assistant') {
                $chatService->addAssistantMessage($historyMessage['content']);
            }
        }

        $chatService->addUserMessage($this->conversationMessage->message);

        $chatService->setTemperature(0.8);
        $chatService->setMaxTokens(1000);

        $result = $chatService->chat();

        if (isset($result['error'])) {
            $this->conversationMessage->update([
                'response' => 'I apologize, but I seem to be having trouble responding right now. Please try again.',
            ]);
        } else {
            $this->conversationMessage->update([
                'response' => $result['completion'],
            ]);
        }

        event(new CharacterChatResponseEvent($this->conversationMessage->fresh()));
    }

    /**
     * Build the system prompt for the character chat.
     */
    protected function buildSystemPrompt(Character $character, Conversation $conversation): string
    {
        $book = $character->book;

        $prompt = "You are role-playing as {$character->name} from a story. ";
        $prompt .= 'Stay completely in character at all times. Never break character or acknowledge that you are an AI. ';
        $prompt .= "Respond as if you ARE this character, with their personality, mannerisms, and way of speaking.\n\n";

        $prompt .= "=== YOUR CHARACTER ===\n";
        $prompt .= "Name: {$character->name}\n";

        if ($character->age) {
            $prompt .= "Age: {$character->age}\n";
        }

        if ($character->gender) {
            $prompt .= "Gender: {$character->gender}\n";
        }

        if ($character->nationality) {
            $prompt .= "Nationality: {$character->nationality}\n";
        }

        if ($character->description) {
            $prompt .= "About You: {$character->description}\n";
        }

        if ($character->backstory) {
            $prompt .= "Your Backstory: {$character->backstory}\n";
        }

        if ($book) {
            $prompt .= "\n=== THE STORY ===\n";
            $prompt .= "Story Title: {$book->title}\n";

            if ($book->genre) {
                $prompt .= "Genre: {$book->genre}\n";
            }

            if ($book->plot) {
                $prompt .= "Story Plot: {$book->plot}\n";
            }

            if ($book->summary) {
                $prompt .= "Story Summary: {$book->summary}\n";
            }

            $otherCharacters = $book->characters()
                ->where('id', '!=', $character->id)
                ->get();

            if ($otherCharacters->isNotEmpty()) {
                $prompt .= "\n=== OTHER CHARACTERS YOU KNOW ===\n";
                foreach ($otherCharacters as $otherChar) {
                    $prompt .= "- {$otherChar->name}";
                    if ($otherChar->description) {
                        $prompt .= ": {$otherChar->description}";
                    }
                    $prompt .= "\n";
                }
            }

            $latestChapter = $book->chapters()->orderBy('sort', 'desc')->first();
            if ($latestChapter && $latestChapter->summary) {
                $prompt .= "\n=== CURRENT EVENTS ===\n";
                $prompt .= "What's happening now: {$latestChapter->summary}\n";
            }
        }

        $prompt .= "\n=== GUIDELINES ===\n";
        $prompt .= "- Speak naturally as your character would.\n";
        $prompt .= "- Reference events, relationships, and details from the story when relevant.\n";
        $prompt .= "- Show your character's personality through your responses.\n";
        $prompt .= "- Keep responses conversational and engaging.\n";
        $prompt .= "- If asked about things outside your story, respond as your character would (perhaps confused or curious).\n";
        $prompt .= "- Never say 'As an AI...' or anything that breaks character.\n";

        return $prompt;
    }
}
