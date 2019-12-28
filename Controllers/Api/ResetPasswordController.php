<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordReset;
use App\Models\User;
use App\Notifications\PasswordResetRequest;
use Exception;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Create password reset
     *
     * @param Request $request [string] email
     * @return JsonResponse [string] message
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);
        $user = User::where('email', $request->email)->first();
        if (empty($user)) {
            return response()->json([
                'error' => 'We can\'t find a user with that e-mail address.'
            ], 404);
        }
        try {
            $passwordReset = PasswordReset::updateOrCreate([
                'email' => $user->email
            ], [
                'token' => Str::random(60)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        }
        $password = Str::random(10);
        if ($user && $passwordReset) {
            $user->password = bcrypt($password);
            $user->save();
            $user->notify(
                new PasswordResetRequest($password)
            );
        }

        return response()->json([
            'data' => [
                'message' => 'We have e-mailed your new password!',
            ],
        ]);
    }
}
