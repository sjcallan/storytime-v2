<?php

namespace App\Traits\Service;

trait Gettable
{
    /**
     * @param array $fields
     * @param array $options
     */
    public function getAll(array $fields= null, array $options = null) {
        return $this->repository->getAll($fields, $options);
    }

    /**
     * @param int $id
     * @param array $fields
     * @param array $options
     */
    public function getById(int $id, array $fields = null, array $options = null) {
        return $this->repository->getById($id, $fields, $options);
    }
}