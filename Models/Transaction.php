<?php

namespace App\Models;

use App\Traits\ScopesTrait;
use Depsimon\Wallet\Transaction as TransactionOriginal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QBuilder;
use Kyslik\ColumnSortable\Sortable;

/**
 * Class Transaction
 * @package App\Models
 *
 * @method static QBuilder select(array|mixed $columns = ['*'])
 * @method static Model findOrFail(mixed $id, array $columns = ['*'])
 */
class Transaction extends TransactionOriginal
{
    use ScopesTrait;
    use Sortable;

    const TRANSACTION_TYPES = ['deposit' => 'Deposit', 'withdraw' => 'Withdraw'];

    //
    protected $fillable = [
        'wallet_id', 
        'receiver_user_id', 
        'sender_user_id',
        'operator_id',
        'amount', 
        'currency', 
        'status', 
        'hash', 
        'type', 
        'accepted', 
        'meta'
    ];

    /**
     * @return BelongsTo
     */
    public function receiver()
    {
        return $this->belongsTo(config('wallet.user_model', User::class), 'receiver_user_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function sender()
    {
        return $this->belongsTo(config('wallet.user_model', User::class), 'sender_user_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function operator()
    {
        return $this->belongsTo(config('wallet.user_model', User::class), 'operator_id', 'id');
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
                ->where('receiver_user_id', '=', $user->id)
                ->orWhere('sender_user_id', '=', $user->id)
                ->orWhereHas('receiver', function($query) use ($user) {
                    $query->where('manager_id', '=', $user->id);
                })->orWhereHas('sender', function($query) use ($user) {
                    $query->where('manager_id', '=', $user->id);
                })->orWhereHas('operator', function($query) use ($user) {
                    $query->where('manager_id', '=', $user->id);
                });
        }
        return $query;
    }

    /**
     * @param Builder $query
     * @param string $filter
     *
     * @return mixed
     */
    public function scopeFilterTypes($query, $filter = null)
    {
        $filter = $filter ?? request()->get('type', null);
        if (!empty($filter)) {
            return $query
                ->where('type', '=', $filter);
        }

        return $query;
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
                ->whereHas('receiver', function($query) use ($filter) {
                    $query->where('name', 'LIKE', "%{$filter}%");
                })->orWhereHas('sender', function($query) use ($filter) {
                    $query->where('name', 'LIKE', "%{$filter}%");
                })->orWhereHas('operator', function($query) use ($filter) {
                    $query->where('name', 'LIKE', "%{$filter}%");
                });
        }
        return $query;
    }
}
