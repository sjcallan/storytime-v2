<?php

namespace App\Services\OpenAi;

use App\Services\Ai\Contracts\AiApiServiceInterface;
use App\Services\Ai\OpenAi\ChatService as BaseChatService;
use App\Services\RequestLog\RequestLogService;

/**
 * @deprecated Use App\Services\Ai\OpenAi\ChatService instead.
 *
 * This class is kept for backwards compatibility.
 */
class ChatService extends BaseChatService
{
    public function __construct(AiApiServiceInterface $apiService, RequestLogService $requestLogService)
    {
        parent::__construct($apiService, $requestLogService);
    }
}
