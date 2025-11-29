<?php

namespace App\Services\Conversation;

use App\Repositories\Conversation\ConversationMessageRepository;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;

class ConversationMessageService
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Repositories\Conversation\ConversationMessageRepository */
    protected $repository;

    /**
     * @param \App\Repositories\Conversation\ConversationMessageRepository $conversationMessageRepository
     */
    public function __construct(ConversationMessageRepository $conversationMessageRepository ) 
    {
        $this->repository = $conversationMessageRepository;
    }

}