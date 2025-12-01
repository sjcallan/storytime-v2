<?php

namespace App\Traits\Service;

trait Deletable
{
    /**
     * @param string $id
     */
    public function deleteById(string $id) {
        return $this->repository->deleteById($id);
    }


}