<?php

namespace App\Traits;


trait ScopesTrait {

    /**
     * @param $query
     * @return mixed
     */
    public function scopeSorted($query) {
        if (request()->get('sort')) {
            return $query->sortable();
        }

        return $query->orderByDesc('created_at');
    }
}
