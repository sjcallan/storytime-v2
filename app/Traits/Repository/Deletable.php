<?php

namespace App\Traits\Repository;

trait Deletable
{
    /**
     * @param string $id
     */
    public function deleteById(string $id) {
        $item = $this->model->where('id', $id)->first();
        return $item->delete();
    }
}