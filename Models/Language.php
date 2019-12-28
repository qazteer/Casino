<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;

/**
 * Class Language
 * @package App\Models
 *
 * @method static Builder pluck($column, $key = null)
 */
class Language extends Model
{
    /**
     * @return HasMany
     */
    public function users()
    {
        return $this->hasMany('App\Models\User');
    }
}
