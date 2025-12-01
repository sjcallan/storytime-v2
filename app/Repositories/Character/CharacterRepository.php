<?php

namespace App\Repositories\Character;

use App\Models\Character;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;

class characterRepository
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Models\Character */
    protected $model;

    /** @var */
    protected $query;

    /**
     * @param \App\Models\Character $model
     */
    public function __construct(Character $model) 
    {
        $this->model = $model;
        $this->query = $model;
    }

    /**
     * @param string $userId
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

    /**
     * @param string $userId
     * @param array $fields
     * @param array $options
     */
    public function getAllByChapterId(string $chapterId, array $fields = null, array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);
        
        $this->query = $this->query->join('chapter_character', 'chapter_character.character_id', 'characters.id');
        return $this->query->where('chapter_character.chapter_id', $chapterId)->get();
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
        
        return $this->query->where('characters.user_id', $userId)->get();
    }
}