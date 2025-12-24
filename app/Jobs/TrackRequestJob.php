<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrackRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    protected string $bookId;

    protected string $userId;

    protected ?string $profileId;

    protected string $chapterId;

    protected int $responseStatusCode;

    protected float $responseTime;

    protected string $itemType;

    protected string $request;

    protected string $response;

    protected string $rawResponse;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $bookId,
        string $chapterId,
        string $userId,
        string $itemType,
        string $request,
        string $response,
        string $rawResponse,
        int $responseStatusCode,
        float $responseTime,
        ?string $profileId = null
    ) {
        $this->chapterId = $chapterId;
        $this->bookId = $bookId;
        $this->userId = $userId;
        $this->profileId = $profileId;
        $this->itemType = $itemType;
        $this->request = $request;
        $this->response = $response;
        $this->responseStatusCode = $responseStatusCode;
        $this->responseTime = $responseTime;
        $this->rawResponse = $rawResponse;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::debug('TRACK REQUEST: '.$this->itemType);

        /** @var \App\Services\RequestLog\RequestLogService */
        $requestLogService = app(\App\Services\RequestLog\RequestLogService::class);

        $parsed = $requestLogService->parseResponseForStore(json_decode($this->response, true));

        $requestLogService->store([
            'user_id' => $this->userId,
            'profile_id' => $this->profileId,
            'book_id' => $this->bookId,
            'item_type' => $this->itemType,
            'chapter_id' => $this->chapterId,
            'request' => $this->request,
            'response' => $this->rawResponse,
            'response_time' => $this->responseTime,
            'response_status_code' => $this->responseStatusCode,
        ] + $parsed);
        Log::debug('TRACK REQUEST: '.$this->itemType.' COMPLETE');
    }
}
