<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CurrencyService;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\DateRangeTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use DateRangeTrait;

    protected $currencyService;

    public function __construct()
    {
        $this->currencyService = new CurrencyService();
    }

    // Dashboard
    public function dashboard()
    {
        $currentUser = Auth::user();

        $date = $this->dateRange();

        $transactions = Transaction::select(DB::raw('sum(amount) as amount, type'))
            ->filterManager($currentUser)
            ->whereBetween('created_at', [$date['start'], $date['end']])
            ->groupBy('type')
            ->get();
        $transactions = $transactions->mapWithKeys(function ($item) {
            return [$item->type => $item->amount];
        });

        $games = [];
//        if (is_array(config('games.global.categories'))) {
//            $gamesEfficiency = DB::table('game_bets as bets')
//                ->join('games', 'games.code', '=', 'bets.gameReference')
//                ->join('game_categories as cats', 'cats.id', '=', 'games.category_id')
//                ->select(DB::raw(
//                    'games.id as id,
//                    bets.gameReference as reference,
//                    games.name as game,
//                    cats.name as category,
//                    SUM(bets.amount) - SUM(bets.win_amount) as efficiency'
//                ))
//                ->whereIn('cats.name', config('games.global.categories'))
//                ->whereBetween('bets.created_at', [$date['start'], $date['end']])
//                ->groupBy('reference', 'id', 'category', 'game')
//                ->get();
//
//            $gamesEfficiency = $gamesEfficiency->sortBy('efficiency')->values();
//            foreach (config('games.global.categories') as $category) {
//                $catCollection = $gamesEfficiency->whereIn('category', $category);
//                $games[$category]['worst'] = $catCollection->first(function () {
//                        return 1;
//                    }) ?? 'No game';
//                $games[$category]['best'] = $catCollection->last(function () {
//                        return 1;
//                    }) ?? 'No game';
//            }
//            $games = collect($games)->chunk(2)->toArray();
//        }

        $players = User::filterByRole(User::ROLE_PLAYERS_ID)
            ->manager($currentUser)
            ->whereBetween('created_at', [$date['start'], $date['end']])
            ->count();
        $deposit = $transactions['deposit'] ?? 0;
        $withdraw = $transactions['withdraw'] ?? 0;
        $currency = config('crm.currency');
        $profit = $deposit - $withdraw;

        //Converting
        $deposit = $this->currencyService->toDollars($deposit);
        $withdraw = $this->currencyService->toDollars($withdraw);
        $profit = $this->currencyService->toDollars($profit);

        $data = [
            'deposit' => $deposit,
            'withdraw' => $withdraw,
            'profit' => $profit,
            'players' => $players,
            'user_type_id' => User::ROLE_PLAYERS_ID,
            'games' => $games,
            'date' => $date,
            'currency' => !empty($currency->currency) ? $currency->currency : config('crm.currency'),
        ];
        return view('admin.dashboard',$data);
    }

    public function welcome()
    {
        return view('welcome');
    }
}