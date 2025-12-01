<?php

namespace App\Repositories\ReadingLog;

use App\Models\ReadingLog;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;

class ReadingLogRepository
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Models\ReadingLog */
    protected $model;

    /** @var */
    protected $query;

    /**
     * @param \App\Models\ReadingLog $model
     */
    public function __construct(ReadingLog $model) 
    {
        $this->model = $model;
        $this->query = $model;
    }

    /**
     * 
     */
    public function getBookmark(string $userId, string $bookId, array $fields = null, array $options = null)
    {
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query->where('user_id', $userId)->where('book_id', $bookId)->orderBy('created_at', 'asc')->last();
    }
}