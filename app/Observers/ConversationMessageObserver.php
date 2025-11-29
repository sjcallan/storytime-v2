<?php

namespace App\Observers;

use App\Events\ConversationMessage\ConversationMessageCreatedEvent;
use App\Events\ConversationMessage\ConversationMessageDeletedEvent;
use App\Events\ConversationMessage\ConversationMessageUpdatedEvent;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Event;

class ConversationMessageObserver
{
    /**
     * Handle the ConversationMessage "created" event.
     */
    public function created(ConversationMessage $conversationMessage): void
    {
        Event::dispatch(new ConversationMessageCreatedEvent($conversationMessage));
    }

    /**
     * Handle the ConversationMessage "updated" event.
     */
    public function updated(ConversationMessage $conversationMessage): void
    {
        Event::dispatch(new ConversationMessageUpdatedEvent($conversationMessage));
    }

    /**
     * Handle the ConversationMessage "deleted" event.
     */
    public function deleted(ConversationMessage $conversationMessage): void
    {
        Event::dispatch(new ConversationMessageDeletedEvent($conversationMessage));
    }
}
