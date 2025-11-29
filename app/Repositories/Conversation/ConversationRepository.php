<?php

namespace App\Repositories\Conversation;

use App\Models\Conversation;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;

class ConversationRepository
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Models\Conversation */
    protected $model;

    /** @var */
    protected $query;

    /**
     * @param \App\Models\Conversation $model
     */
    public function __construct(Conversation $model) 
    {
        $this->model = $model;
        $this->query = $model;
    }

    /**
     * @param int $userId
     * @param array $fields
     * @param array $options
     */
    public function getByUserCharacterId(int $userId, int $characterId, array $fields = null, array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);
        
        return $this->query->where('user_id', $userId)->where('character_id', $characterId)->first();
    }
}