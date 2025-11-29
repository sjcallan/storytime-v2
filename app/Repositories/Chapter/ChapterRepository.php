<?php

namespace App\Repositories\Chapter;

use App\Models\Chapter;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;

class ChapterRepository
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Models\Chapter */
    protected $model;

    /** @var */
    protected $query;

    /**
     * @param \App\Models\Chapter $model
     */
    public function __construct(Chapter $model) 
    {
        $this->model = $model;
        $this->query = $model;
    }

    /**
     * @param int $userId
     * @param array $fields
     * @param array $options
     */
    public function getAllByUserId(int $userId, array $fields = null, array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);
        
        return $this->query->where('owner_id', $userId)->get();
    }

    /**
     * @param string $bookId
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