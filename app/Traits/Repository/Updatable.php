<?php

namespace App\Traits\Repository;

use Illuminate\Support\Facades\Log;

trait Updatable
{

    /**
     * @param int $id
     * @param array $data
     * @param array $options
     */
    public function updateById(int $id, array $data, array $options = null)
    {
        $model = $this->model->where('id', $id)->first();
        foreach($data AS $field => $value) {
            $model->$field = $value;
        }

        if($options && array_key_exists('events', $options) && $options['events'] == false) {
            Log::debug('saving quietly: ' . json_encode($data));
            $model->saveQuietly();
        } else {
            $model->save();
        }

        return $this->model->where('id', $id)->first();
    }

}