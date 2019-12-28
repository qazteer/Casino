<?php

namespace App\Traits;

use App\Helpers\CurrencyService;
use App\Models\GameBet;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Trait FundMovementTrait
 * @package App\Traits
 */
trait FundMovementTrait {

    /**
     * @var array $code => $message
     */
    private $errorList = [
        101 => 'No user sent',
        102 => 'No user found',
        201 => 'Insufficient funds',
        202 => 'Bet already exists',
        203 => 'Bet already settled',
        204 => 'Bet does not exist',
        999 => 'Unknown error',
    ];

    /**
     * It is used for processing winnings
     * @param array $data = [
     *                  'transaction_id' => string,
     *                  'ref_id'         => string,
     *                  'gameReference'  => string,
     *                  'amount'         => float,
     * ];
     *
     * @return array balance
     */
    protected function processCredit($data)
    {
        $user = request()->user();

        if (empty($user)) {
            return $this->error(102);
        }

        $bet = GameBet::where('ref_id', '=', $data['ref_id'])->first();

        if (empty($bet)) {
            return $this->error(204);
        }

        $alreadyProcessed = GameBet::where('transaction_id', '=', $data['transaction_id'])->first();
        $amount           = (new CurrencyService())->toCents($data['amount']);

        try {
            if ($alreadyProcessed) {
                return $this->error(203);
            }

            DB::beginTransaction();
            GameBet::create([
                'user_id'        => $user->id,
                'transaction_id' => $data['transaction_id'],
                'ref_id'         => $data['ref_id'],
                'round_id'       => time(),
                'gameReference'  => $data['gameReference'] ?? '',
                'win_amount'     => $amount,
                'status'         => GameBet::STATUS['won'],
            ]);

            $bet->status = GameBet::STATUS['won'];
            $bet->save();

            DB::table('wallets')->where('user_id', '=', $user->id)->increment('balance', $amount);
            $wallet = DB::table('wallets')->where('user_id', '=', $user->id)->first('balance');

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();

            return $this->error(999, $e->getMessage());
        }

        return [
            'balance'   => $wallet->balance,
        ];
    }

    /**
     * It is used for processing bets
     * @param array $data = [
     *                  'transaction_id' => string,
     *                  'ref_id'         => string,
     *                  'gameReference'  => string,
     *                  'amount'         => float,
     * ];
     *
     * @return array balance
     */
    protected function processDebit($data)
    {
        $user = request()->user();

        if (empty($user)) {
            return $this->error(102);
        }

        $alreadyProcessed = GameBet::where('transaction_id', '=', $data['transaction_id'])->first();
        $amount           = (new CurrencyService())->toCents($data['amount']);

        if ($user->wallet->balance < $amount) {
            return $this->error(201);
        }

        try {
            if ($alreadyProcessed) {
                return $this->error(202);
            }

            DB::beginTransaction();
            GameBet::create([
                'user_id'        => $user->id,
                'transaction_id' => $data['transaction_id'],
                'ref_id'         => $data['ref_id'],
                'round_id'       => time(),
                'gameReference'  => $data['gameReference'] ?? '',
                'amount'         => $amount,
            ]);

            DB::table('wallets')->where('user_id', '=', $user->id)->decrement('balance', $amount);
            $wallet = DB::table('wallets')->where('user_id', '=', $user->id)->first('balance');

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();

            return $this->error(999, $e->getMessage());
        }

        return [
            'balance'   => $wallet->balance,
        ];
    }

    /**
     * It is used for aborting transactions
     * @param array $data = [
     *                  'transaction_id' => string,
     *                  'amount'         => float,
     * ];
     *
     * @return array balance
     */
    protected function processAbort($data)
    {
        $user = request()->user();

        if (empty($user)) {
            return $this->error(102);
        }

        $transaction = GameBet::where('transaction_id', '=', $data['transaction_id'])->first();
        $amount      = (new CurrencyService())->toCents($data['amount']);

        if (empty($transaction)) {
            return $this->error(204);
        }

        switch ($transaction->status) {
            case GameBet::STATUS['unsettled']:
                $user->wallet->balance += $amount;
                break;

            case GameBet::STATUS['won']:
                if (!empty($transaction->amount)) {
                    $user->wallet->balance += $amount;
                    break;
                }
                $user->wallet->balance -= $amount;
                break;

            default:
                return $this->error(203);
        }

        $user->wallet->save();

        $transaction->status = GameBet::STATUS['refund'];
        $transaction->save();

        return [
            'balance'   => $user->wallet->balance,
        ];
    }

    /**
     * @param int           $code
     * @param null|string   $message
     *
     * @return array
     */
    private function error($code, $message = null)
    {
        return [
            'error'     => $code,
            'message'   => $message ?? $this->errorList[$code],
        ];
    }
}