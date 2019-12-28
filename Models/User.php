<?php

namespace App\Models;

use App\Http\Controllers\Admin\DashboardController;
use App\Traits\ScopesTrait;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laratrust\Traits\LaratrustUserTrait;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Depsimon\Wallet\HasWallet;
use Webpatser\Uuid\Uuid;
use Kyslik\ColumnSortable\Sortable;

/**
 * Class User
 * @package App\Models
 *
 * @method static Builder create(array $attributes = [])
 * @method static Builder orWhere($column, $operator = null, $value = null)
 * @method static Builder where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static Builder whereHas(string $relation, Closure $callback, string $operator = '>=', int $count = 1)
 * @method static Model find(int $id, array $columns = ['*'])
 * @method static Model findOrFail(mixed $id, array $columns = ['*'])
 */
class User extends Authenticatable implements JWTSubject
{
    use HasWallet;
    use LaratrustUserTrait;
    use Notifiable;
    use ScopesTrait;
    use Sortable;

    const ROLE_ADMINISTRATOR_ID = 1;
    const ROLE_MANAGER_ID = 2;
    const ROLE_PLAYERS_ID = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'manager_id',
        'name',
        'email',
        'password',
        'avatar',
        'first_name',
        'last_name',
        'phone',
        'status',
        'country_id',
        'language_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            if (empty($model->game_token)) {
                $model->game_token = (string) Uuid::generate(4);
            }
        });
    }

    /**
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Move credits to this account
     *
     * @param integer $amount
     * @param string $receiver_user_id
     * @param string $sender_user_id
     * @param string $currency
     * @param string $status
     * @param string $type
     * @param array $meta
     * @param bool $accepted
     */
    public function deposit($amount, $receiver_user_id='', $sender_user_id='', $currency='', $status='approved', $type = 'deposit', $meta = [], $accepted = true)
    {
        if ($accepted) {
            $this->wallet->balance += $amount;
            $this->wallet->save();
        } elseif (! $this->wallet->exists) {
            $this->wallet->save();
        }

        $this->wallet->transactions()
            ->create([
                'receiver_user_id' => $receiver_user_id,
                'sender_user_id' => $sender_user_id,
                'operator_id' => $sender_user_id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'hash' => uniqid('lwch_'),
                'type' => $type,
                'accepted' => $accepted,
                'meta' => $meta
            ]);
    }

    /**
     * Attempt to move credits from this account
     *
     * @param integer $amount
     * @param string $receiver_user_id
     * @param string $sender_user_id
     * @param string $currency
     * @param string $status
     * @param string $type
     * @param array $meta
     * @param bool $shouldAccept
     */
    public function withdraw($amount, $receiver_user_id='', $sender_user_id='', $currency='', $status='approved', $type = 'withdraw', $meta = [], $shouldAccept = true)
    {
        $accepted = $shouldAccept ? $this->canWithdraw($amount) : true;

        if ($accepted) {
            $this->wallet->balance -= $amount;
            $this->wallet->save();

            $this->wallet->transactions()
            ->create([
                'receiver_user_id' => $receiver_user_id,
                'sender_user_id' => $sender_user_id,
                'operator_id' => $receiver_user_id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'hash' => uniqid('lwch_'),
                'type' => $type,
                'accepted' => $accepted,
                'meta' => $meta
            ]);
        } elseif (! $this->wallet->exists) {
            $this->wallet->save();
        }

        
    }

    /**
     * @return HasMany
     */
    public function receivers()
    {
        return $this->hasMany('App\Models\Transaction', 'receiver_user_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function senders()
    {
        return $this->hasMany('App\Models\Transaction', 'sender_user_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function operators()
    {
        return $this->hasMany('App\Models\Transaction', 'operator_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    /**
     * @return BelongsTo
     */
    public function language()
    {
        return $this->belongsTo('App\Models\Language');
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
                ->where('name', 'LIKE', "%{$filter}%");
        }
        return $query;
    }

    /**
     * Scope for checking if current user has manager rights
     *
     * @param Builder $query
     * @param User $user
     * @return mixed
     */
    public function scopeManager($query, $user)
    {
        if ($user->hasRole('manager')) {
            return $query
                    ->where('manager_id', '=', $user->id);
        }
        return $query;
    }

    /**
     * Scope for filtering users by role
     * @see DashboardController
     *
     * @param  Builder  $query
     * @param int $role_id
     *
     * @return mixed
     */
    public function scopeFilterByRole($query, $role_id = null)
    {
        $role_id = $role_id ?? request()->get('role', null);
        if (!empty($role_id)) {
            return $query
                ->whereHas('roles', function($query) use ($role_id) {
                    $query->where('id', $role_id);
                });
        }
        return $query;
    }

    /**
     * Scope for ordering users by their creation
     *
     * @param Builder $query
     *
     * @return mixed
     */
    public function scopeOrderByRequest($query)
    {
        return $query->orderBy(
            request()->get('sortBy','created_at'),
            request()->get('orderBy', 'desc')
        );
    }

    /**
     * @param Builder $query
     * @param string $sign
     * @param int $balance
     *
     * @return mixed
     */
    public function scopeFilterByBalance($query, $sign = null, $balance = null)
    {
        $balance = $balance ?? request()->get('balance', null);
        $sign = $sign ?? request()->get('sign', '');
        if (in_array($sign, ['=', '>', '<']) && !empty($balance)) {
            $balance = intval($balance)*100;
            return $query
                ->whereHas('wallet', function($query) use ($sign, $balance) {
                    $query->where('wallets.balance', $sign, $balance);
                });
        }
        return $query;
    }

    /**
     * ColumnSortable overriding (advanced)
     * Need for sorting by role
     *
     * @param $query
     * @param $direction
     *
     * @return mixed
     */
    public function roleSortable($query, $direction)
    {
        return $query->leftJoin('role_user', 'users.id', '=', 'role_user.user_id')
            ->orderBy('role_user.role_id', $direction);
    }

    /**
     * Find user by email
     *
     * @param int $identifier
     * @return mixed
     */
    public function findForPassport($identifier)
    {
        return User::orWhere('email', $identifier)->where('status', 1)->first();
    }
}
