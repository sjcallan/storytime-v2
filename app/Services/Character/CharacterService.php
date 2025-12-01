<?php

namespace App\Services\Character;

use App\Repositories\Character\CharacterRepository;
use App\Services\OpenAi\ChatService;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;

class CharacterService
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Repositories\Character\CharacterRepository */
    protected $repository;

    /** @var \App\Services\OpenAi\ChatService */
    protected $chatService;

    /**
     * @param \App\Repositories\Character\CharacterRepository $characterRepository
     */
    public function __construct(CharacterRepository $characterRepository, ChatService $chatService ) 
    {
        $this->repository = $characterRepository;
        $this->chatService = $chatService;
    }
    
    /**
     * @param string $bookId
     * @param array $fields
     * @param array $options
     */
    public function getAllByBookId(string $bookId, array $fields = null, array $options = null)
    {
        return $this->repository->getAllByBookId($bookId, $fields, $options);
    }

    /**
     * @param string $chapterId
     * @param array $fields
     * @param array $options
     */
    public function getAllByChapterId(string $chapterId, array $fields = null, array $options = null)
    {
        return $this->repository->getAllByChapterId($chapterId, $fields, $options);
    }

    /**
     * @param string $userId
     * @param array $fields
     * @param array $options
     */
    public function getAllByUserId(string $userId, array $fields = null, array $options = null)
    {
        return $this->repository->getAllByUserId($userId, $fields, $options);
    }
}