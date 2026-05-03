<?php

namespace App\Traits;

trait HasSoftDeleteActions
{
    public function scopeTrash($query)
    {
        return $query->onlyTrashed();
    }

    public function scopeWithTrash($query)
    {
        return $query->withTrashed();
    }
}