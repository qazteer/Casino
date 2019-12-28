<?php

namespace App\Http\Controllers\Api;

use App\Helpers\CurrencyService;
use App\Helpers\ImageService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends BaseApiController
{
    /**
     * @return JsonResponse
     */
    public function login()
    {
        $credentials = \request(['email', 'password']);

        try {
            if (! $token = auth()->attempt($credentials)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        return $this->respondWithToken($token);
    }

    /**
     * @return JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * @return JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $this->validate($request, [
                'name'              => 'required|unique:users|min:4|max:20',
                'email'             => 'required|unique:users',
                'first_name'        => 'required|max:20',
                'last_name'         => 'required|max:20',
                'country'           => 'required|integer',
                'language'          => 'required|integer',
                'password'          => 'required|min:8',
                'confirm_password'  => 'required|same:password',
                'avatar'            => 'image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $error) {
                $errors[$field] = $error[0];
            }
            return response()->json([
                'errors' => $errors,
            ], 401);
        }

        $path = (new ImageService())->handleUploadedImage($request, '', '/images/avatar/', 'avatar');

        $role = User::ROLE_PLAYERS_ID;

        $manager = User::with('roles')
            ->filterByRole(User::ROLE_MANAGER_ID)
            ->first();

        $user = User::create([
            'manager_id'    => $manager->id,
            'name'          => $request->input('name'),
            'first_name'    => $request->input('first_name'),
            'last_name'     => $request->input('last_name'),
            'phone'         => $request->input('phone') ?? '',
            'avatar'        => $path,
            'email'         => $request->input('email'),
            'country_id'    => $request->input('country'),
            'language_id'   => $request->input('language'),
            'password'      => bcrypt($request->input('password')),
        ]);

        $user->attachRole($role);

        $success['token'] =  $user->game_token;

        //Create Wallet and add Deposit
        $deposit = (new CurrencyService())->toCents( 0);
        $receiverUserId = $user->id;
        $senderUserId = $manager->id;
        $currency = config('crm.currency');
        $status = 'approved';

        $user->deposit($deposit, $receiverUserId, $senderUserId, $currency, $status);

        return $this->login();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $this->validate($request, [
                'name'       => 'unique:users|min:4|max:20',
                'email'      => 'unique:users',
                'first_name' => 'max:20',
                'last_name'  => 'max:20',
                'country'    => 'integer',
                'language'   => 'integer',
                'password'   => 'min:8',
                'avatar'     => 'image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $error) {
                $errors[$field] = $error[0];
            }
            return response()->json([
                'errors' => $errors,
            ], 401);
        }

        $user = Auth::user();

        $path = (new ImageService())->handleUploadedImage($request, $user, '/images/avatar/', 'avatar');

        $user->name        = $request->input('name', $user->name);
        $user->email       = $request->input('email', $user->email);
        $user->first_name  = $request->input('first_name', $user->first_name);
        $user->last_name   = $request->input('last_name', $user->last_name);
        $user->phone       = $request->input('phone', $user->phone);
        $user->country_id  = $request->input('country', $user->country_id);
        $user->language_id = $request->input('language', $user->language_id);

        if (!empty($request->input('password'))) {
            $user->password = bcrypt($request->input('password'));
        }

        if (!empty($path)) {
            $user->avatar = $path;
        }

        $user->save();

        return response()->json([
            'data' => [
                'message' => 'User data was successfully updated!',
            ],
        ], 200);
    }

}
