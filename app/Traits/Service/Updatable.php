<?php

namespace App\Traits\Service;

trait Updatable
{

    /**
     * @param string $id
     * @param array $data
     * @param array $options
     */
    public function updateById(string $id, array $data, array $options = null)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->repository->updateById($id, $data, $options);
    }

}