<?php

namespace App\Http\Controllers\Api;

use App\Helpers\CurrencyService;
use App\Http\Resources\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserController extends BaseApiController
{
    /**
     * @return User
     */
    public function me()
    {
        return User::make(auth()->user());
    }

    /**
     * @return JsonResponse
     */
    public function balance()
    {
        $wallet = DB::table('wallets')->where('user_id', '=', auth()->id())->first();

        return response()->json([
            'balance' => (new CurrencyService())->toDollars($wallet->balance),
        ]);
    }
}
