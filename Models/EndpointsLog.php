<?php

namespace App\Models;

use App\Traits\ScopesTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QBuilder;
use Kyslik\ColumnSortable\Sortable;

/**
 * Class EndpointsLog
 * @package App\Models
 *
 * @method static Builder create(array $attributes = [])
 * @method static Builder updateOrCreate(array $attributes, array $values = [])
 * @method static QBuilder orderByDesc(string $column)
 * @method static Model findOrFail(mixed $id, array $columns = ['*'])
 */
class EndpointsLog extends Model
{
    use ScopesTrait;
    use Sortable;

    /**
     * @const array
     */
    const TYPES = ['Inbound', 'Outbound'];

    /**
     * @const array
     */
    const METHODS = ['GET', 'POST'];

    /**
     * @var array
     */
    protected $fillable = [
        'url',
        'type',
        'method',
        'provider',
        'ip',
        'route',
        'headers',
        'body',
        'response',
    ];

    /**
     * @param $query
     * @param string|null $filter
     * @return mixed
     */
    public function scopeMethods($query, $filter = null)
    {
        $filter = $filter ?? request()->get('method', null);
        if (!empty($filter)) {
            return $query
                ->where('method', '=', $filter);
        }

        return $query;
    }

    /**
     * @param $query
     * @param string|null $filter
     * @return mixed
     */
    public function scopeSearchByUrl($query, $filter = null)
    {
        $filter = $filter ?? request()->get('url_search', null);
        if (!empty($filter)) {
            return $query
                ->where('url', 'LIKE', "%{$filter}%");
        }
        return $query;
    }

    /**
     * @param $query
     * @param string|null $filter
     * @return mixed
     */
    public function scopeSearchById($query, $filter = null)
    {
        $filter = $filter ?? request()->get('id_search', null);
        if (!empty($filter)) {
            return $query
                ->where('id', '=', $filter);
        }
        return $query;
    }

    /**
     * @param $query
     * @param string|null $filter
     * @return mixed
     */
    public function scopeProviders($query, $filter = null)
    {
        $filter = $filter ?? request()->get('provider', null);
        if (!empty($filter)) {
            return $query
                ->where('provider', '=', $filter)
                ->orWhere('route', 'LIKE', "%{$filter}%");
        }

        return $query;
    }

    /**
     * @param $query
     * @param string|null $filter
     * @return mixed
     */
    public function scopeTypes($query, $filter = null)
    {
        $filter = $filter ?? request()->get('type', null);
        if (!empty($filter)) {
            return $query
                ->where('type', '=', $filter);
        }

        return $query;
    }
}
