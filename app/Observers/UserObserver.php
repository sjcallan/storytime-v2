<?php

namespace App\Observers;

use App\Events\User\UserCreatedEvent;
use App\Events\User\UserDeletedEvent;
use App\Events\User\UserUpdatedEvent;
use App\Models\User;
use Illuminate\Support\Facades\Event;

class UserObserver
{
    /**
     * Constructor
     */
    public function __construct(
    ) {}

    /**
     * Handle the User "created" event.
     *
     * @return void
     */
    public function created(User $user)
    {
        Event::dispatch(new UserCreatedEvent($user));
    }

    /**
     * Handle the User "updated" event.
     *
     * @return void
     */
    public function updated(User $user)
    {
        Event::dispatch(new UserUpdatedEvent($user));
    }

    /**
     * Handle the User "deleted" event.
     *
     * @return void
     */
    public function deleted(User $user)
    {
        Event::dispatch(new UserDeletedEvent($user));
    }
}
