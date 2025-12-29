<?php

namespace App\Listeners\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;

class LogLockoutListener
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $request = $event->request;
        $email = $request->input('email', '');
        $user = User::query()->where('email', $email)->first();

        LoginAttempt::query()->create([
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'lockout',
        ]);
    }
}
