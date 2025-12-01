<?php

namespace App\Repositories\Book;

use App\Models\Book;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;

class BookRepository
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Models\Book */
    protected $model;

    /** @var */
    protected $query;

    /**
     * @param \App\Models\Book $model
     */
    public function __construct(Book $model) 
    {
        $this->model = $model;
        $this->query = $model;
    }

    /**
     * @param string $userId
     * @param array $fields
     * @param array $options
     */
    public function getAllByUserId(string $userId, array $fields = null, array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);
        
        return $this->query->where('user_id', $userId)->get();
    }

    /**
     * @param array $fields
     * @param array $options
     */
    public function getAllPublished(array $fields = null, array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);
        
        return $this->query->where('is_published', 1)->where('status', 'complete')->get();
    }
}