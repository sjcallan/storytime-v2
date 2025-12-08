<?php

namespace App\Services\RequestLog;

use App\Models\Chapter;
use App\Repositories\RequestLog\RequestLogRepository;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;

class RequestLogService
{
    use Creatable, Deletable, Gettable, Updatable;

    /** @var \App\Repositories\RequestLog\RequestLogRepository */
    protected $repository;

    public function __construct(RequestLogRepository $userRepository)
    {
        $this->repository = $userRepository;
    }

    /**
     * Store a request log with validation
     */
    public function store(array $data, ?array $options = null)
    {
        $data = $this->validateAndSanitizeData($data);

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->repository->store($data, $options);
    }

    /**
     * Validate and sanitize request log data
     */
    protected function validateAndSanitizeData(array $data): array
    {
        if (isset($data['chapter_id']) && ! empty($data['chapter_id'])) {
            $chapterExists = Chapter::where('id', $data['chapter_id'])->exists();

            if (! $chapterExists) {
                $data['chapter_id'] = null;
            }
        } else {
            $data['chapter_id'] = null;
        }

        return $data;
    }

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

    public function getAllByBookId(string $bookId, ?array $fields = null, ?array $options = null)
    {
        return $this->repository->getAllByBookId($bookId, $fields, $options);
    }
}
