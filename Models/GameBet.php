<?php

namespace App\Models;

use App\Traits\ScopesTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kyslik\ColumnSortable\Sortable;

/**
 * Class GameBet
 * @package App\Models
 *
 * @method static Builder create(array $attributes = [])
 * @method static Builder where($column, $operator = null, $value = null, $boolean = 'and')
 */
class GameBet extends Model
{
    use ScopesTrait;
    use Sortable;

    const STATUS = [
        'unsettled' => 1,
        'won'       => 2,
        'lost'      => 3,
        'refund'    => 4,
    ];

    const TYPE = ['Debit', 'Credit'];

    protected $fillable = [
        'user_id',
        'transaction_id',
        'ref_id',
        'gameReference',
        'round_id',
        'status',
        'amount',
        'win_amount',
    ];

    protected $attributes = [
        'status' => self::STATUS['unsettled']
    ];

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    /**
     * @return BelongsTo
     */
    public function game()
    {
        return $this->belongsTo('App\Models\Game', 'code');
    }

    /**
     * Scope for searching
     *
     * @param Builder $query
     * @param string $filter
     *
     * @return mixed
     */
    public function scopeSearch($query, $filter = null)
    {
        $filter = $filter ?? request()->get('search', null);
        if (!empty($filter)) {
            return $query
                ->where('transaction_id', 'LIKE', "%{$filter}%")
                ->orWhere('ref_id', 'LIKE', "%{$filter}%");
        }
        return $query;
    }

    /**
     * @param $query
     * @param int $filter
     *
     * @return Builder
     */
    public function scopeStatus($query, $filter = null)
    {
        /** @var Builder $query */
        $filter = $filter ?? request()->get('status', null);
        if (!empty($filter)) {
            return $query->where('status', '=', $filter);
        }
        return $query;
    }

    /**
     * @param $query
     * @param int $filter
     *
     * @return Builder
     */
    public function scopeTypes($query, $filter = null)
    {
        /** @var Builder $query */
        $filter = $filter ?? request()->get('type', null);
        if (!empty($filter)) {
            if ($filter == GameBet::TYPE[0]) {
                return $query->where('amount', '>', 0);
            }
            return $query
                ->where('win_amount', '>', 0);
        }
        return $query;
    }

    /**
     * @param Builder $query
     * @param User $user
     *
     * @return mixed
     */
    public function scopeFilterManager($query, $user)
    {
        if ($user->hasRole('manager')) {
            return $query
                ->where('user_id', '=', $user->id)
                ->orWhereHas('user', function($query) use ($user) {
                    $query->where('manager_id', '=', $user->id);
                });
        }
        return $query;
    }
}
