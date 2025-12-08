<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterCharacter;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Genre;
use App\Models\ReadingLog;
use App\Models\RequestLog;
use App\Models\User;
use App\Observers\BookObserver;
use App\Observers\ChapterCharacterObserver;
use App\Observers\ChapterObserver;
use App\Observers\CharacterObserver;
use App\Observers\ConversationMessageObserver;
use App\Observers\ConversationObserver;
use App\Observers\GenreObserver;
use App\Observers\ReadingLogObserver;
use App\Observers\RequestLogObserver;
use App\Observers\UserObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    /**
     * Temporarily disabled - BookService has CharacterBuilderService dependency
     * which extends BuilderService causing segfault during service injection.
     * TODO: Fix BuilderService dependencies or make these queued jobs.
     */
    protected $listen = [
        \App\Events\Book\BookUpdatedEvent::class => [
            \App\Listeners\Chapter\CreateFirstChapterListener::class,
        ],
        \App\Events\Character\CharacterCreatedEvent::class => [
            \App\Listeners\Character\GenerateCharacterPortraitListener::class,
        ],
        \App\Events\Character\AllCharactersPortraitsCreatedEvent::class => [
            \App\Listeners\Book\GenerateBookCoverListener::class,
        ],
        \App\Events\User\UserCreatedEvent::class => [
            \App\Listeners\User\CreateDefaultProfileListener::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        Book::observe(BookObserver::class);
        Chapter::observe(ChapterObserver::class);
        Character::observe(CharacterObserver::class);
        ChapterCharacter::observe(ChapterCharacterObserver::class);
        Conversation::observe(ConversationObserver::class);
        ConversationMessage::observe(ConversationMessageObserver::class);
        Genre::observe(GenreObserver::class);
        ReadingLog::observe(ReadingLogObserver::class);
        RequestLog::observe(RequestLogObserver::class);
    }
}
