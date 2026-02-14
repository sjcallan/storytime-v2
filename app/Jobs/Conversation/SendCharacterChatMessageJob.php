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

        $chatService->setTemperature(0.9);
        $chatService->setMaxTokens(3000);

        $result = $chatService->chat();

        if (isset($result['error'])) {
            $this->conversationMessage->update([
                'response' => 'I apologize, but I seem to be having trouble responding right now. Please try again.',
            ]);
        } else {
            $this->conversationMessage->update([
                'response' => $result['completion'],
                'ai_thinking' => $result['thinking'] ?? null,
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

        $prompt = "You are {$character->name}, having a casual face-to-face conversation. ";
        $prompt .= 'Respond with only spoken dialogue - no actions, no thoughts, no narration. ';
        $prompt .= "Keep responses brief and natural, matching the length of what you're responding to.\n\n";

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

        $prompt .= "\n=== CONVERSATION RULES ===\n";
        $prompt .= "This is a natural face-to-face conversation. You must follow these rules strictly:\n\n";
        $prompt .= "RESPONSE FORMAT:\n";
        $prompt .= "- Only write dialogue - what you would actually SAY out loud.\n";
        $prompt .= "- NEVER include actions, gestures, or movements (no *walks over*, *sighs*, *looks away*, etc.).\n";
        $prompt .= "- NEVER include thoughts or internal monologue.\n";
        $prompt .= "- NEVER include narration or scene descriptions.\n";
        $prompt .= "- NEVER use asterisks, parentheses, or brackets for actions.\n\n";
        $prompt .= "RESPONSE LENGTH:\n";
        $prompt .= "- Match your response length to the user's message length.\n";
        $prompt .= "- Short question = short answer. Long message = can be longer.\n";
        $prompt .= "- Keep responses brief and natural, like a real conversation.\n";
        $prompt .= "- One to three sentences is usually enough.\n\n";
        $prompt .= "ENGAGEMENT:\n";
        $prompt .= "- Keep the conversation moving forward naturally based on your personality.\n";
        $prompt .= "- Sometimes ask a question, sometimes share a thought, sometimes react - vary your approach.\n";
        $prompt .= "- Be genuinely curious when it fits your character.\n";
        $prompt .= "- Don't always end with a question - let the conversation breathe.\n\n";
        $prompt .= "TONE:\n";
        $prompt .= "- Speak casually and naturally as your character would.\n";
        $prompt .= "- Show personality through word choice.\n";
        $prompt .= "- Reference the story only when relevant.\n";
        $prompt .= "- Never break character or acknowledge being an AI.\n";

        return $prompt;
    }
}
