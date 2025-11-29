<?php

namespace App\Traits\Repository;

trait Deletable
{
    /**
     * @param int $id
     */
    public function deleteById(int $id) {
        $item = $this->model->where('id', $id)->first();
        return $item->delete();
    }
}