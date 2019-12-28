<?php

namespace App\Models;

use App\Traits\ScopesTrait;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QBuilder;
use Kyslik\ColumnSortable\Sortable;

/**
 * Class Game
 * @package App\Models
 *
 * @method static Builder create(array $attributes = [])
 * @method static Builder updateOrCreate(array $attributes, array $values = [])
 * @method static Builder where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static Model findOrFail(mixed $id, array $columns = ['*'])
 */
class Game extends Model
{
    use ScopesTrait;
    use Sortable;

    /**
     * @var string
     */
    protected $table = 'games';

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'provider_id',
        'code',
        'code_mobile',
        'image',
        'is_featured',
        'active',
        'params',
    ];

    /**
     * @const array
     */
    const STATUS = ['Inactive', 'Active'];

    /**
     * @return Repository|mixed
     */
    public static function getTypes(){
        return config('games.global.types');
    }

    /**
     * @return BelongsToMany
     */
    public function categories(){
        return $this->belongsToMany('App\Models\GameCategory', 'game_game_category', 'game_id', 'game_category_id');
    }

    /**
     * @return BelongsTo
     */
    public function provider(){
        return $this->belongsTo('App\Models\GameProvider');
    }

    /**
     * @param $query
     * @param int $filter
     *
     * @return QBuilder
     */
    public function scopeStatus($query, $filter = -1)
    {
        /** @var Builder $query */
        $filter = ($filter==-1) ? request()->get('status', -1) : $filter;
        if ($filter != -1) {
            return $query->where('active', '=', $filter);
        }
        return $query;
    }

    /**
     * @param $query
     * 
     * @return QBuilder
     */
    public function scopeActive($query)
    {
        /** @var QBuilder $query */
        return $query->where('active', '=', 1);
    }

    /**
     * @param $query
     * @param int $filter
     * @return QBuilder
     */
    public function scopeProviders($query, $filter = null)
    {
        $filter = $filter ?? request()->get('provider', null);
        if (!empty($filter)) {
            return $query->where('provider_id', '=', $filter);
        }
        return $query;
    }

    /**
     * @param $query
     * @param string $filter
     * @return QBuilder
     */
    public function scopeTypes($query, $filter = null)
    {
        $filter = $filter ?? request()->get('type', null);
        if (!empty($filter)) {
            return $query->where('type', '=', $filter);
        }
        return $query;
    }

    /**
     * @param $query
     * @param int $filter
     *
     * @return QBuilder
     */
    public function scopeFilterCategories($query, $filter = null)
    {
        $filter = $filter ?? request()->get('category', null);
        if (!empty($filter)) {
            return $query
                ->whereHas('categories', function($query) use ($filter) {
                    $query->where('id', $filter);
                });
        }
        return $query;
    }

    /**
     * Scope for searching
     *
     * @param Builder $query
     * @param string $filter
     *
     * @return QBuilder
     */
    public function scopeSearch($query, $filter = null)
    {
        $filter = $filter ?? request()->get('code', null);
        if (!empty($filter)) {
            return $query
                ->where('name', 'LIKE', "%{$filter}%")
                ->orWhere('code', 'LIKE', "%{$filter}%");
        }
        return $query;
    }

    /**
     * @param $query
     * @return Builder
     */
    public function scopeOrderByFeatured($query)
    {
        /** @var Builder $query */
        return $query->orderBy('is_featured', 'desc');
    }

    /**
     * @param $data
     * @return array
     */
    public function getUpdateData($data)
    {
        $update_data = [];
        $cases = [];
        $codes = [];
        $params = [];

        foreach ($data['paths'] as $name => $path) {
            $code = $name;
            $cases[] = "WHEN '{$code}' then ?";
            $params[] = $path;
            $codes[] = "'".$code."'";
        }

        $codes = implode(',', $codes);
        $cases = implode(' ', $cases);

        $update_data['params'] = $params;
        $update_data['codes'] = $codes;
        $update_data['cases'] = $cases;

        return $update_data;
    }
}
