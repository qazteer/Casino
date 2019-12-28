<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QBuilder;
use Kyslik\ColumnSortable\Sortable;

/**
 * Class GameProvider
 * @package App\Models
 *
 * @method static Builder create(array $attributes = [])
 * @method static Builder firstOrCreate(array $attributes, array $values = [])
 * @method static QBuilder pluck($column, $key = null)
 * @method static Model findOrFail(mixed $id, array $columns = ['*'])
 */
class GameProvider extends Model
{

    use Sortable;

    protected $fillable = [
        'name',
    ];

    /**
     * @return HasMany
     */
    public function games()
    {
        return $this->hasMany('App\Models\Game', 'provider_id');
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public static function getProviderIdByName(string $name)
    {
        $category = GameProvider::firstOrCreate( ['name' => $name]);
        return $category->id;
    }
}
