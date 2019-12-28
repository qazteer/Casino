<?php


namespace App\Http\Controllers\Endpoints;


use App\Helpers\CurrencyService;
use App\Models\GameBet;

class EvolutionGamesEndpointController
{

    public function __construct()
    {

    }

    /**
     * Response;
     * status = true/false
     * balance = 10.00
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function balance()
    {
        $user = request()->user();
        $hash = request()->input('hash');

        if (empty($user)) {
            return response()->json(['status' => false,]);
        }

        return response()->json([
            'status'  => true,
            'balance' => (new CurrencyService())->toDollars($user->wallet->balance),
            'hash'    => $this->generateHash(request()->all()),
        ]);
    }

    /**
     * Request content;
     * tid = unique transaction id
     * reference_id = game reference id
     * game_id = game id
     * user_id = your user id
     * amount = 10.00 decimal 10,2
     * currency = Run Game Currency parameter,
     * game_name = game name
     * hash = generate hash ?? not clear
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function debit()
    {
        $user = request()->user();

        if (empty($user)) {
            return response()->json(['status' => false, 'bet' => false, 'error' => 'User not found']);
        }

        $request = request()->all();

        //TODO: check user hash
        if (empty($request['hash'])){
            return response()->json(['status' => false,]);
        }

        $alreadyProcessed = GameBet::where('transaction_id', '=', $request['tid'])->first();
        $amount           = (new CurrencyService())->toCents($request['amount']);

        try {
            if ( ! $alreadyProcessed) {

                \DB::beginTransaction();
                GameBet::create([
                    'user_id'        => $user->id,
                    'transaction_id' => $request['tid'],
                    'round_id'       => time(),
                    'gameReference'  => $request['game_id'],
                    'amount'         => $amount,
                ]);

                $user->wallet->balance -= $amount;
                $user->wallet->save();
                \DB::commit();
            } else {
                return response()->json(['status' => false, 'bet' => false]);
            }
        } catch (\Exception $e) {
            \DB::rollback();

            return response()->json(['status' => false, 'error' => 'Internal error: ' . $e->getMessage()]);
        }

        return response()->json([
            'status'       => true,
            'bet'          => true,
            'balance'      => (new CurrencyService())->toDollars($user->wallet->balance),
            'reference_id' => $request['reference_id'],
            'hash'         => $this->generateHash(request()->all()),
        ]);
    }

    /**
     * Request content;
     * tid = unique transaction id
     * reference_id = game reference id
     * game_id = game id
     * user_id = your user id
     * amount = 10.00 decimal 10,2
     * currency = Run Game Currency parameter,
     * game_name = game name
     * hash = generate hash ?? not clear
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function credit()
    {
        $user = request()->user();

        if (empty($user)) {
            return response()->json(['status' => false,]);
        }

        $request          = request()->all();

        //TODO: check user hash
        if (empty($request['hash'])){
            return response()->json(['status' => false,]);
        }

        $alreadyProcessed = GameBet::where('transaction_id', '=', $request['tid'])->first();
        $amount           = (new CurrencyService())->toCents($request['amount']);

        try {
            if ( ! $alreadyProcessed) {
                \DB::beginTransaction();

                $createdGameBet = GameBet::create([
                    'user_id'        => $user->id,
                    'transaction_id' => $request['tid'],
                    'round_id'       => time(),
                    'gameReference'  => $request['game_id'],
                    'win_amount'     => $amount,
                    'status'         => GameBet::STATUS['won'],
                ]);

                $user->wallet->balance += $amount;
                $user->wallet->save();
                \DB::commit();
            } else {
                return response()->json(['status' => false,]);
            }
        } catch (\Exception $e) {
            \DB::rollback();

            return response()->json(['status' => false, 'error' => 'Internal error: ' . $e->getMessage()]);
        }

        return response()->json([
            'status'       => true,
            'balance'      => (new CurrencyService())->toDollars($user->wallet->balance),
            'reference_id' => $request['reference_id'],
            'hash'         => $this->generateHash(request()->all()),
        ]);
    }

    private function generateHash($parameters = [])
    {

        if (isset($parameters['hash'])) {
            unset($parameters['hash']);
        }

        $secret = hash("sha256", $this->apiToken, true);

        ksort($parameters);

        $hmac_base = implode('', array_values($parameters));

        return hash_hmac('sha256', $hmac_base, $secret);
    }
}