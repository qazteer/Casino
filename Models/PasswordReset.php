<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PasswordReset
 * @package App\Models
 *
 * @method static Builder updateOrCreate(array $attributes, array $values = [])
 */
class PasswordReset extends Model
{
    protected $fillable = [
        'email',
        'token',
    ];
}
