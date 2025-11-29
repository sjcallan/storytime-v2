<?php

namespace App\Traits\Repository;

trait Creatable
{
    /**
     * @param array $data
     * @param array $options
     */
    public function store(array $data, array $options = null) {
        $model = new $this->model;
        foreach($data AS $field => $value) {
            $model->$field = $value;
        }

        if($options && array_key_exists('events', $options) && $options['events'] == false) {
            $model->saveQuietly();
        } else {
            $model->save();
        }

        $newItemId = $model->id;
        return $this->model->where('id', $newItemId)->first();
    }
}