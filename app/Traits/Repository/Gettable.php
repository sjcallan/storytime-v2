<?php

namespace App\Traits\Repository;

trait Gettable
{
    protected function reset()
    {
        $this->query = $this->model;
    }

    protected function setFields(?array $fields = null)
    {
        if (! $fields) {
            return;
        }

        $this->query = $this->query->select($fields);
    }

    protected function setOptions(?array $options = null)
    {
        if (! $options) {
            return;
        }

        foreach ($options as $type => $option) {
            if ($type == 'with') {
                $this->query = $this->query->with($option);
            }

            if ($type == 'withCount') {
                $this->query = $this->query->withCount($option);
            }

            if ($type == 'withTrashed') {
                $this->query = $this->query->withTrashed();
            }
        }
    }

    public function getAll(?array $fields = null, ?array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        // if($options && array_key_exists('with', $options)) {
        //     return $this->query->with($options['with'])->get();
        // }

        return $this->query->get();
    }

    /**
     * @param  int  $id
     */
    public function getById(string|int $id, ?array $fields = null, ?array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query->where('id', $id)->first();
    }
}
