<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;

class UserRepository
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Models\User */
    protected $model;

    /** @var */
    protected $query;

    /**
     * @param \App\Models\User $model
     */
    public function __construct(User $model) 
    {
        $this->model = $model;
        $this->query = $model;
    }
}