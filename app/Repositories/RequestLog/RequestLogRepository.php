<?php

namespace App\Repositories\RequestLog;

use App\Models\RequestLog;
use App\Models\User;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;

class RequestLogRepository
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Models\RequestLog */
    protected $model;

    /** @var */
    protected $query;

    /**
     * @param \App\Models\RequestLog $model
     */
    public function __construct(RequestLog $model) 
    {
        $this->model = $model;
        $this->query = $model;
    }

    /**
     * @param int $userId
     * @param array $fields
     * @param array $options
     */
    public function getAllByBookId(string $bookId, array $fields = null, array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);
        
        return $this->query->where('book_id', $bookId)->get();
    }
}