<?php

namespace App\Listeners\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;

class LogFailedLoginListener
{
    public function __construct(protected Request $request) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        LoginAttempt::query()->create([
            'user_id' => $user?->id,
            'email' => $event->credentials['email'] ?? '',
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'status' => 'failed',
        ]);
    }
}
