<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CurrencyService;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Traits\DateRangeTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class TransactionController extends Controller
{
    use DateRangeTrait;

    /**
     * @var CurrencyService
     */
    protected $currencyService;

    /**
     * TransactionController constructor.
     */
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
        //
        $currentUser = Auth::user();

        $date = $this->dateRange();

        $filters = request()->only([
            'type',
            'search',
        ]);

        $transactions = Transaction::with('receiver', 'sender', 'operator')
            ->filterManager($currentUser)
            ->filterTypes()
            ->search()
            ->whereBetween('wallet_transactions.created_at', [$date['start'], $date['end']])
            ->sorted()
            ->paginate(20);

        $transactionsInfo = Transaction::select(DB::raw('sum(amount) as amount, type'))
            ->whereBetween('created_at', [$date['start'], $date['end']])
            ->filterTypes()
            ->groupBy('type')
            ->get();
        $transactionsInfo = $transactionsInfo->mapWithKeys(function ($item) {
            return [$item->type => $item->amount];
        });

        foreach (Transaction::TRANSACTION_TYPES as $type => $title) {
            $transactionsInfo[$type] = $transactionsInfo[$type] ?? 0;
        }
        //TODO: Учитывать состояние кошелька на момент запроса и добавить в расчет тотал профита
        $total = (!empty($filters['type'])) ? $transactionsInfo[$filters['type']] : $transactionsInfo['deposit'] - $transactionsInfo['withdraw'];
        $params = [
            'title' => 'Users Transactions',
            'transactions' => $transactions,
            'currencyService' => $this->currencyService,
            'date' => $date,
            'filters' => $filters,
            'types' => Transaction::TRANSACTION_TYPES,
            'total' => $total,
            'currency' => config('crm.currency'),
        ];
        return view('admin.transactions.index')->with($params);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
        return view('admin.transactions.transactions_create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
        return view('admin.transactions.transactions_delete');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
        try {
            $transaction = Transaction::findOrFail($id);

            $params = [
                'title' => 'Edit Transaction',
                'transaction' => $transaction,
                'currencyService' => $this->currencyService,
            ];
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return view('admin.transactions.transactions_edit')->with($params);
    }

    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse|Response
     * @throws ValidationException
     */
    public function update(Request $request, $id)
    {
        //
        try {
            $transaction = Transaction::findOrFail($id);
            $this->validate($request, [
                'status' => 'required',
            ]);

            $transaction->status = $request->input('status');
            $transaction->save();
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return redirect()->route('transactions.index')->with('success', "The transaction <strong>$transaction->id</strong> has successfully been updated.");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     */
    public function destroy($id)
    {
        //

    }
}
