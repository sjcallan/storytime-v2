<?php

namespace App\Services\User;

use App\Repositories\User\UserRepository;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;

class UserService
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Repositories\User\UserRepository */
    protected $repository;

    /**
     * @param \App\Repositories\User\UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository ) 
    {
        $this->repository = $userRepository;
    }
}