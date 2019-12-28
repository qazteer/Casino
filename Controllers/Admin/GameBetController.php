<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CurrencyService;
use App\Models\GameBet;
use App\Http\Controllers\Controller;
use App\Traits\DateRangeTrait;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class GameBetController extends Controller
{
    use DateRangeTrait;

    protected $currencyService;

    public function __construct()
    {
        $this->currencyService = new CurrencyService();
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $currentUser = Auth::user();

        $date = $this->dateRange();

        $filters = request()->only([
            'search',
            'type',
            'status',
        ]);

        $bets = GameBet::with('user', 'game')
            ->filterManager($currentUser)
            ->whereBetween('game_bets.created_at', [$date['start'], $date['end']])
            ->search()
            ->types()
            ->status()
            ->sorted()
            ->paginate(15);

        $params = [
            'title' => 'Bets',
            'bets' => $bets,
            'currencyService' => $this->currencyService,
            'date'  => $date,
            'filters' => $filters,
            'types' => GameBet::TYPE,
            'status' => GameBet::STATUS,
        ];

        return view('admin.bets.index')->with($params);
    }
}
