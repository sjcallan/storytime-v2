<?php

namespace App\Traits\Service;

trait Gettable
{
    public function getAll(?array $fields = null, ?array $options = null)
    {
        return $this->repository->getAll($fields, $options);
    }

    /**
     * @param  int  $id
     */
    public function getById(string|int $id, ?array $fields = null, ?array $options = null)
    {
        return $this->repository->getById($id, $fields, $options);
    }
}
