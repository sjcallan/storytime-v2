<?php

namespace App\Services\RequestLog;

use App\Repositories\RequestLog\RequestLogRepository;
use App\Repositories\User\UserRepository;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;

class RequestLogService
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Repositories\RequestLog\RequestLogRepository */
    protected $repository;

    /**
     * @param \App\Repositories\RequestLog\RequestLogRepository $userRepository
     */
    public function __construct(RequestLogRepository $userRepository ) 
    {
        $this->repository = $userRepository;
    }

    /**
     * @param array $response
     */
    public function parseResponseForStore(array $response)
    {
        return [
            'open_ai_id' => $response['id'],
            'model' => $response['model'],
            'prompt_tokens' => $response['prompt_tokens'],
            'completion_tokens' => $response['completion_tokens'],
            'total_tokens' => $response['total_tokens'],
            'cost_per_token' => $response['cost_per_token'],
            'total_cost' => $response['total_cost'],
        ];
    }

    /**
     * @param string $bookId
     * @param array $fields
     * @param array $options
     */
    public function getAllByBookId(string $bookId, array $fields = null, array $options = null)
    {
        return $this->repository->getAllByBookId($bookId, $fields, $options);
    }
}