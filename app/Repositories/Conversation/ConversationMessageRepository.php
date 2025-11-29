<?php

namespace App\Repositories\Conversation;

use App\Models\ConversationMessage;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;

class ConversationMessageRepository
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Models\ConversationMessage */
    protected $model;

    /** @var */
    protected $query;

    /**
     * @param \App\Models\ConversationMessage $model
     */
    public function __construct(ConversationMessage $model) 
    {
        $this->model = $model;
        $this->query = $model;
    }
}