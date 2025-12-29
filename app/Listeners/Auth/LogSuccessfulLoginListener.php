<?php

namespace App\Listeners\Auth;

use App\Models\LoginAttempt;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class LogSuccessfulLoginListener
{
    public function __construct(protected Request $request) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        LoginAttempt::query()->create([
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'status' => 'success',
        ]);
    }
}
