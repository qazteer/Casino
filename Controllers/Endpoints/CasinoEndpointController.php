<?php

namespace App\Http\Controllers\Endpoints;

use App\Http\Controllers\Controller;
use App\Models\GameBet;

class CasinoEndpointController extends Controller
{
    protected static $availableMethodList = ['getPlayerInfo', 'getBalance', 'bet', 'win', 'refundTransaction'];

    /** @var array code => message */
    protected static $errorList = [
        0 => 'Internal error',
        -1 => 'General error',
        100 => 'Unspecified Error',
        101 => 'The player token is invalid',
        102 => 'The player token expired',
        103 => 'The authentication credentials for the API are incorrect',
        200 => 'Not enough credits',
        201 => 'Invalid amount',
        202 => 'Transaction not found',
        2000 => 'Token is invalid or old',
        4000 => 'Unknown token',
        5000 => 'Non existing transaction',
        9999 => 'Round does not exist',
    ];

    /**
     * CasinoEndpointController constructor.
     */
    public function __construct()
    {
        $this->middleware(['xml', 'check.wacs']);
    }

    public function handle()
    {
        $method = request()->input('method.@attributes.name');

        if (!in_array($method, static::$availableMethodList) || !is_callable([$this, $method])) {
            return $this->error($method, 'Method is not available', 0);
        }

        return call_user_func([$this, $method]);
    }

    /**
     * @return mixed
     */
    protected function getPlayerInfo()
    {
        $user = request()->user();

        $data = [
            'result' => [
                '@attributes' => [
                    'name' => 'getPlayerInfo',
                    'success' => 1,
                ],
                'returnset' => [
                    'token' => [
                        '@attributes' => [
                            'value' => $user->game_token
                        ]
                    ],
                    'loginName' => [
                        '@attributes' => [
                            'value' => $user->name
                        ]
                    ],
                    'currency' => [
                        '@attributes' => [
                            'value' => config('crm.currency')
                        ]
                    ],
                    'balance' => [
                        '@attributes' => [
                            'value' => $user->balance
                        ]
                    ]
                ],
            ]
        ];

        return response()->xml($data, 200, [], 'message');
    }

    /**
     * Get player balance
     */
    protected function getBalance()
    {
        $user = request()->user();

        $data = [
            'result' => [
                '@attributes' => [
                    'name' => 'getBalance',
                    'success' => 1,
                ],
                'returnset' => [
                    'token' => [
                        '@attributes' => [
                            'value' => $user->game_token
                        ]
                    ],
                    'balance' => [
                        '@attributes' => [
                            'value' => $user->balance
                        ]
                    ]
                ],
            ]
        ];

        return response()->xml($data, 200, [], 'message');
    }

    /**
     * Save transaction, update balance
     */
    protected function bet()
    {
        $user = request()->user();

        $transactionId = \request()->input('method.params.transactionId.@attributes.value');
        $amount = \request()->input('method.params.amount.@attributes.value');
        $gameReference = \request()->input('method.params.gameReference.@attributes.value');
        $roundId = \request()->input('method.params.roundId.@attributes.value');

        $alreadyProcessed = GameBet::where('transaction_id', '=', $transactionId)->first();

        try {
            if (!$alreadyProcessed) {
                \DB::beginTransaction();
                $createdGameBet = GameBet::create([
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                    'round_id' => $roundId,
                    'gameReference' => $gameReference,
                    'amount' => $amount,
                ]);

                $user->wallet->balance -= $amount;
                $user->wallet->save();
                \DB::commit();
            }
        }catch(\Exception $e){
            \DB::rollback();

            return $this->error('bet', 'Internal Error', 0);
        }

        return response()->xml([
            'result' => [
                '@attributes' => [
                    'name' => 'bet',
                    'success' => 1,
                ],
                'returnset' => [
                    'token' => [
                        '@attributes' => [
                            'value' => request()->user()->game_token
                        ]
                    ],
                    'balance' => [
                        '@attributes' => [
                            'value' => $user->wallet->balance
                        ]
                    ],
                    'transactionId' => [
                        '@attributes' => [
                            'value' => $transactionId
                        ]
                    ],
                    'alreadyProcessed' => [
                        '@attributes' => [
                            'value' => $alreadyProcessed ? 'true' : 'false'
                        ]
                    ]
                ],
            ]
        ], 200, [], 'message');
    }

    /**
     * Save win transaction, upd balance
     */
    protected function win()
    {
        $user = request()->user();

        $transactionId = \request()->input('method.params.transactionId.@attributes.value');
        $amount = \request()->input('method.params.amount.@attributes.value');
        $gameReference = \request()->input('method.params.gameReference.@attributes.value');
        $roundId = \request()->input('method.params.roundId.@attributes.value');

        $alreadyProcessed = GameBet::where('transaction_id', '=', $transactionId)->first();
        try {
            if (!$alreadyProcessed) {
                \DB::beginTransaction();

                $createdGameBet = GameBet::create([
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                    'round_id' => $roundId,
                    'gameReference' => $gameReference,
                    'win_amount' => $amount,
                    'status' => GameBet::STATUS['won'],
                ]);

                $user->wallet->balance += $amount;
                $user->wallet->save();
                \DB::commit();
            }
        }catch(\Exception $e){
            \DB::rollback();

            return $this->error('win', 'Internal Error', 0);
        }

        $data = [
            'result' => [
                '@attributes' => [
                    'name' => 'bet',
                    'success' => 1,
                ],
                'returnset' => [
                    'token' => [
                        '@attributes' => [
                            'value' => \request()->user()->game_token
                        ]
                    ],
                    'balance' => [
                        '@attributes' => [
                            'value' => \request()->user()->balance
                        ]
                    ],
                    'transactionId' => [
                        '@attributes' => [
                            'value' => $transactionId
                        ]
                    ],
                    'alreadyProcessed' => [
                        '@attributes' => [
                            'value' => $alreadyProcessed ? 'true' : 'false'
                        ]
                    ]
                ],
            ]
        ];

        return response()->xml($data, 200, [], 'message');
    }

    /**
     * Refund transaction, upd balance
     */
    protected function refundTransaction()
    {
//        return $this->error('refundTransaction', 'Not implemented yet', 0);
        $user = request()->user();

        $transactionId = \request()->input('method.params.transactionId.@attributes.value');
        $amount = \request()->input('method.params.amount.@attributes.value');
        $gameReference = \request()->input('method.params.gameReference.@attributes.value');
        $roundId = \request()->input('method.params.roundId.@attributes.value');

        $refundedTransactionId = \request()->input('method.params.refundedTransactionId.@attributes.value');

        /** @var GameBet $toRefundBet */
        $toRefundBet = GameBet::where('transaction_id', '=', $refundedTransactionId)->first();

        if (!$toRefundBet) return $this->error('refundTransaction', "Transaction with id='{$refundedTransactionId}' doesn’t exists.", 5000);

        if ($toRefundBet->status !== GameBet::STATUS['unsettled']) {
            // already processed
            return response()->xml([
                'result' => [
                    '@attributes' => [
                        'name' => 'refundTransaction',
                        'success' => 1
                    ],
                    'returnset' => [
                        'token' => ['@attributes' => ['value' => $user->game_token]],
                        'balance' => ['@attributes' => ['value' => $user->balance]],
                        'transactionId' => ['@attributes' => ['value' => $transactionId]],
                        'alreadyProcessed' => ['@attributes' => ['value' => 'true']]
                    ]
                ]
            ], 200, [], 'message');
        }

        try {
            \DB::beginTransaction();
            $user->wallet->balance += $amount;
            $user->wallet->save();
            $toRefundBet->status = GameBet::STATUS['refund'];
            $toRefundBet->save();

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();

            return $this->error('refundTransaction', 'Internal error', 0);
        }

        return response()->xml([
            'result' => [
                '@attributes' => [
                    'name' => 'refundTransaction',
                    'success' => 1
                ],
                'returnset' => [
                    'token' => ['@attributes' => ['value' => $user->game_token]],
                    'balance' => ['@attributes' => ['value' => $user->balance]],
                    'transactionId' => ['@attributes' => ['value' => $transactionId]],
                    'alreadyProcessed' => ['@attributes' => ['value' => 'false']]
                ]
            ]
        ], 200, [], 'message');
    }

    protected function error($method, $message, $code)
    {
        $errorMsg = [
            'result' => [
                '@attributes' => [
                    'name' => $method,
                    'success' => 0,
                ],
                'returnset' => [
                    'error' => [
                        '@attributes' => [
                            'value' => $message
                        ]
                    ],
                    'errorCode' => [
                        '@attributes' => [
                            'value' => $code
                        ]
                    ],
                ],
            ]
        ];


        return response()->xml($errorMsg, 200, [], 'message');
    }

}
