<?php

namespace App\Traits\Service;

trait Creatable
{
    /**
     * @param array $data
     * @param array $options
     */
    public function store(array $data, array $options = null) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->repository->store($data, $options);
    }
}