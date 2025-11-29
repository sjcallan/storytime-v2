<?php

namespace App\Observers;

use App\Events\RequestLog\RequestLogCreatedEvent;
use App\Events\RequestLog\RequestLogDeletedEvent;
use App\Events\RequestLog\RequestLogUpdatedEvent;
use App\Models\RequestLog;
use Illuminate\Support\Facades\Event;

class RequestLogObserver
{
    /**
     * Handle the RequestLog "created" event.
     */
    public function created(RequestLog $requestLog): void
    {
        Event::dispatch(new RequestLogCreatedEvent($requestLog));
    }

    /**
     * Handle the RequestLog "updated" event.
     */
    public function updated(RequestLog $requestLog): void
    {
        Event::dispatch(new RequestLogUpdatedEvent($requestLog));
    }

    /**
     * Handle the RequestLog "deleted" event.
     */
    public function deleted(RequestLog $requestLog): void
    {
        Event::dispatch(new RequestLogDeletedEvent($requestLog));
    }
}
