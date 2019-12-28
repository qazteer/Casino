<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GameSession
 * @package App\Models
 *
 * @method static Builder create(array $attributes = [])
 * @method static Builder where($column, $operator = null, $value = null, $boolean = 'and')
 */
class GameSession extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'uuid',
        'game_code',
    ];
}
