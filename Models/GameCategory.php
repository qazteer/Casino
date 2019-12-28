<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QBuilder;
use Kyslik\ColumnSortable\Sortable;

/**
 * Class GameCategory
 * @package App\Models
 *
 * @method static Builder create(array $attributes = [])
 * @method static Builder firstOrCreate(array $attributes, array $values = [])
 * @method static QBuilder pluck($column, $key = null)
 * @method static Model findOrFail(mixed $id, array $columns = ['*'])
 */
class GameCategory extends Model
{

    use Sortable;

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'category_icon',
    ];

    /**
     * @return BelongsToMany
     */
    public function games()
    {
        return $this->belongsToMany(Game::class, 'game_game_category', 'game_category_id', 'game_id');
    }

    /**
     * @param string $name
     *
     * @return mixed|int
     */
    public static function getCategoryIdByName(string $name)
    {
        $category = GameCategory::firstOrCreate( ['name' => $name]);
        return $category->id;
    }
}
