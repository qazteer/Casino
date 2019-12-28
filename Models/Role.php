<?php

namespace App\Models;

use Illuminate\Database\Query\Builder;
use Laratrust\Models\LaratrustRole;

/**
 * Class Role
 * @package App\Models
 *
 * @method static Builder pluck($column, $key = null)
 * @method static Builder find(int $id, array $columns = ['*'])
 * @method static Builder where($column, $operator = null, $value = null, $boolean = 'and')
 */
class Role extends LaratrustRole
{
    //
    protected $fillable = [
        'name', 'display_name', 'description'
    ];

}
