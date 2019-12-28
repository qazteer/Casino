<?php

namespace App\Http\Controllers\Endpoints;

use App\Helpers\CurrencyService;
use App\Http\Controllers\Controller;
use App\Traits\FundMovementTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Webpatser\Uuid\Uuid;

/**
 * Class EvolutionLiveCasinoController
 * @package App\Http\Controllers\Endpoints
 */
class EvolutionLiveCasinoController extends Controller
{
    use FundMovementTrait;

    /**
     * @var array traitError => providerError
     */
    private $providerErrorList = [
        102 => 'INVALID_PARAMETER',
        201 => 'INSUFFICIENT_FUNDS',
        202 => 'BET_ALREADY_EXIST',
        203 => 'BET_ALREADY_SETTLED',
        204 => 'BET_DOES_NOT_EXIST',
        999 => 'UNKNOWN_ERROR',
    ];

    /**
     * EvolutionLiveCasinoController constructor
     */
    public function __construct()
    {

    }

    /**
     * CheckUserRequest / CheckUserResponse
     *
     * Request parameters:
     *  userId:         Player's ID which is sent by Licensee in UserAuthentication call (player.id)
     *  sid:            Player's session ID which is sent by Licensee in UserAuthentication call (session.id)
     *  channel:        Object containing channel details
     *  channel.type:   Channel type for player in Evolution Live Casino
     *                  -> By default "M" for mobile clients, "P" for all other
     *  uuid:           Unique request id, that identifies CheckUserRequest
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function check()
    {
        $user = request()->user();

        if (empty($user)) {
            $this->errorMsg(102);
        }

        $sid = session()->get('sid');

        return response()->json([
            'status' => "OK",
            'sid'    => $sid,
            'uuid'   => (string) Uuid::generate(4),
        ]);
    }

    /**
     * BalanceRequest / StandardResponse
     *
     * Request parameters:
     *  sid:                    Player's session ID
     *  userId:                 Player's ID, assigned by Licensee
     *  currency:               Currency code (ISO 4217 3 letter code)
     *  game:                   Object containing game details
     *                          -> In case of non-game related balance request (e.g user enters lobby) this object will be empty or null
     *                          -> Could be used to apply limits for specific game data, e.g limit by game.type
     *  game.type:              The game type value
     *  game.details:           Object containing additional game details
     *  game.details.table:     Object containing table details for the game
     *  game.details.table.id:  Unique table identifier
     *  game.details.table.vid: Unique virtual table identifier (can be null in case there is no virtual table id)
     *  uuid:                   Unique request id, that identifies BalanceRequest
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function balance()
    {
        $user = request()->user();

        if (empty($user)) {
            $this->errorMsg(102);
        }

        return response()->json([
            'status'  => "OK",
            'balance' => (new CurrencyService())->toDollarsClear($user->wallet->balance),
            'bonus'   => 0.00,
            'uuid'    => (string) Uuid::generate(4),
        ]);
    }

    /**
     * DebitRequest / StandardResponse
     *
     * Request parameters:
     *  sid:                    Player's session ID
     *  userId:                 Player's ID, assigned by Licensee
     *  currency:               Currency code (ISO 4217 3 letter code)
     *  game:                   Object containing game details
     *  game.id:                Unique game round id in Evolution Live Casino
     *                              -> Only provided with DebitRequest/CreditRequest/CancelRequest,
     *                                  not provided with BalanceRequest
     *  game.type:              The game type value
     *  game.details:           Object containing additional game round details
     *  game.details.table:     Object containing table details for the game round
     *  game.details.table.id:  Unique table identifier
     *  game.details.table.vid: Unique virtual table identifier (can be null in case there is no virtual table id)
     *  transaction:            Object containing transaction details
     *  transaction.id:         The unique identifier of transaction (e.g: used to avoid duplicate bets and other validations)
     *  transaction.refId:      Reference identifier for transaction, to be able to link (correlate)
     *                              and/or validate credit/cancel requests to appropriate debit request
     *  transaction.amount:     Amount of transaction, rounded to 2 decimal places
     *  uuid:                   Unique request id, that identifies BalanceRequest
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function debit()
    {
        $request = request()->all();

        $info = [
            'transaction_id' => $request['transaction']['id'],
            'ref_id'         => $request['transaction']['refId'],
            'gameReference'  => $request['game']['id'] ?? '',
            'amount'         => $request['transaction']['amount'],
        ];

        $trait = $this->processDebit($info);

        if (!empty($trait['error'])) {
            return $this->errorMsg($trait['error'], $trait['message']);
        }

        return response()->json([
            'status'    => "OK",
            'balance'   => (new CurrencyService())->toDollarsClear($trait['balance']),
            'bonus'     => 0.00,
            'uuid'      => (string) Uuid::generate(4),
        ]);
    }

    /**
     * CreditRequest / StandardResponse
     *
     * Request parameters:
     *  sid:                    Player's session ID
     *  userId:                 Player's ID, assigned by Licensee
     *  currency:               Currency code (ISO 4217 3 letter code)
     *  game:                   Object containing game details
     *  game.id:                Unique game round id in Evolution Live Casino
     *                              -> Only provided with DebitRequest/CreditRequest/CancelRequest,
     *                                  not provided with BalanceRequest
     *  game.type:              The game type value
     *  game.details:           Object containing additional game round details
     *  game.details.table:     Object containing table details for the game round
     *  game.details.table.id:  Unique table identifier
     *  game.details.table.vid: Unique virtual table identifier (can be null in case there is no virtual table id)
     *  transaction:            Object containing transaction details
     *  transaction.id:         The unique identifier of transaction (e.g: used to avoid duplicate bets and other validations)
     *  transaction.refId:      Reference identifier for transaction, to be able to link (correlate)
     *                              and/or validate credit/cancel requests to appropriate debit request
     *  transaction.amount:     Amount of transaction, rounded to 2 decimal places
     *  uuid:                   Unique request id, that identifies BalanceRequest
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function credit()
    {
        $request = request()->all();

        $info = [
            'transaction_id' => $request['transaction']['id'],
            'ref_id'         => $request['transaction']['refId'],
            'gameReference'  => $request['game']['id'] ?? '',
            'amount'         => $request['transaction']['amount'],
        ];

        $trait = $this->processCredit($info);

        if (!empty($trait['error'])) {
            return $this->errorMsg($trait['error'], $trait['message']);
        }

        return response()->json([
            'status'    => "OK",
            'balance'   => (new CurrencyService())->toDollarsClear($trait['balance']),
            'bonus'     => 0.00,
            'uuid'      => (string) Uuid::generate(4),
        ]);
    }

    /**
     * CancelRequest / StandardResponse
     *
     * Request parameters:
     *  sid:                    Player's session ID
     *  userId:                 Player's ID, assigned by Licensee
     *  currency:               Currency code (ISO 4217 3 letter code)
     *  game:                   Object containing game details
     *  game.id:                Unique game round id in Evolution Live Casino
     *                              -> Only provided with DebitRequest/CreditRequest/CancelRequest,
     *                                  not provided with BalanceRequest
     *  game.type:              The game type value
     *  game.details:           Object containing additional game round details
     *  game.details.table:     Object containing table details for the game round
     *  game.details.table.id:  Unique table identifier
     *  game.details.table.vid: Unique virtual table identifier (can be null in case there is no virtual table id)
     *  transaction:            Object containing transaction details
     *  transaction.id:         The unique identifier of transaction (e.g: used to avoid duplicate bets and other validations)
     *  transaction.refId:      Reference identifier for transaction, to be able to link (correlate)
     *                              and/or validate credit/cancel requests to appropriate debit request
     *  transaction.amount:     Amount of transaction, rounded to 2 decimal places
     *  uuid:                   Unique request id, that identifies BalanceRequest
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function cancel()
    {
        $request = request()->all();

        $info = [
            'transaction_id' => $request['transaction']['id'],
            'amount'         => $request['transaction']['amount'],
        ];

        $trait = $this->processAbort($info);

        if (!empty($trait['error'])) {
            return $this->errorMsg($trait['error'], $trait['message']);
        }

        return response()->json([
            'status'    => "OK",
            'balance'   => (new CurrencyService())->toDollarsClear($trait['balance']),
            'bonus'     => 0.00,
            'uuid'      => (string) Uuid::generate(4),
        ]);
    }

    /**
     * For testing purposes following service should be implemented on test environments
     * CheckUserRequest / CheckUserResponse
     *
     * Request parameters:
     *  userId:         Player's ID which is sent by Licensee in UserAuthentication call (player.id)
     *  sid:            Player's session ID which is sent by Licensee in UserAuthentication call (session.id)
     *  channel:        Object containing channel details
     *  channel.type:   Channel type for player in Evolution Live Casino
     *                  -> By default "M" for mobile clients, "P" for all other
     *  uuid:           Unique request id, that identifies CheckUserRequest
     *
     * @return JsonResponse
     */
    public function sid()
    {
        return response()->json([
            'status'  => "OK",
        ]);
    }

    /**
     * @param  int      $code
     * @param  string   $message
     * @return JsonResponse
     */
    private function errorMsg($code, $message = '')
    {
        $errorMsg = [
            'status'    => $this->providerErrorList[$code],
            'detail'    => $message, //Our error explanation
        ];

        return response()->json($errorMsg);
    }
}