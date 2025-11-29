<?php

namespace App\Traits\Service;

trait Deletable
{
    /**
     * @param int $id
     */
    public function deleteById(int $id) {
        return $this->repository->deleteById($id);
    }


}