<?php

namespace App\Listeners\User;

use App\Events\User\UserCreatedEvent;
use App\Models\User;
use App\Notifications\UserCreatedAdminNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UserCreatedAdminNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $admins = User::where('is_admin', 1)->get();

        foreach ($admins as $admin) {
            $admin->notify(new UserCreatedAdminNotification($event->user));
        }
    }
}
