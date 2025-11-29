<?php

namespace App\Observers;

use App\Events\Conversation\ConversationCreatedEvent;
use App\Events\Conversation\ConversationDeletedEvent;
use App\Events\Conversation\ConversationUpdatedEvent;
use App\Models\Conversation;
use Illuminate\Support\Facades\Event;

class ConversationObserver
{
    /**
     * Handle the Conversation "created" event.
     */
    public function created(Conversation $conversation): void
    {
        Event::dispatch(new ConversationCreatedEvent($conversation));
    }

    /**
     * Handle the Conversation "updated" event.
     */
    public function updated(Conversation $conversation): void
    {
        Event::dispatch(new ConversationUpdatedEvent($conversation));
    }

    /**
     * Handle the Conversation "deleted" event.
     */
    public function deleted(Conversation $conversation): void
    {
        Event::dispatch(new ConversationDeletedEvent($conversation));
    }
}
