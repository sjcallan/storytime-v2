<?php

namespace App\Services\Conversation;

use App\Repositories\Conversation\ConversationRepository;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;

class ConversationService
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Repositories\Conversation\ConversationRepository */
    protected $repository;

    /**
     * @param \App\Repositories\Conversation\ConversationRepository $conversationRepository
     */
    public function __construct(ConversationRepository $conversationRepository ) 
    {
        $this->repository = $conversationRepository;
    }

    /**
     * @param int $userId
     * @param array $fields
     * @param array $options
     */
    public function getByUserCharacterId(int $userId, int $characterId, array $fields = null, array $options = null)
    {
        return $this->repository->getByUserCharacterId($userId, $characterId, $fields, $options);
    }

}