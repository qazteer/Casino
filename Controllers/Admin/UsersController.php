<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CurrencyService;
use App\Helpers\ImageService;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Language;
use App\Models\Role;
use App\Models\User;
use App\Traits\DateRangeTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

class UsersController extends Controller
{
    use DateRangeTrait;

    protected $imageService;
    protected $currencyService;

    public function __construct()
    {
        $this->imageService = new ImageService();
        $this->currencyService = new CurrencyService();
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $date = $this->dateRange();

        $filters = request()->only([
            'search',
            'role',
            'sign',
            'balance',
        ]);

        $currentUser = Auth::user();
        $users = User::with('wallet', 'roles', 'country', 'language')
            ->manager($currentUser)
            ->filterByRole()
            ->filterByBalance()
            ->whereBetween('users.created_at', [$date['start'], $date['end']])
            ->search()
            ->sorted()
            ->paginate(10);

        $params = [
            'title'                 => 'Users Listing',
            'users'                 => $users,
            'current_user_balance'  => $currentUser->balance,
            'currencyService'       => $this->currencyService,
            'roles'                 => Role::pluck('display_name','id'),
            'date'                  => $date,
            'filters'               => $filters,
        ];
        return view('admin.users.index')->with($params);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
        $managers = User::whereHas('roles', function($q){
            $q->where('name', 'manager');
        })->get();

        $params = [
            'title'     => 'Create User',
            'roles'     => Role::pluck('display_name', 'id'),
            'managers'  => $managers,
            'countries' => Country::pluck('name', 'id'),
            'languages' => Language::pluck('name', 'id'),
        ];
        return view('admin.users.users_create')->with($params);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        //
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

        $path = $this->imageService->handleUploadedImage($request, '', '/images/avatar/', 'avatar');

        // If current user is 'administrator' use '$manager_id', if 'manager' use '$current_user_id'
        // Add 'manager' only for 'player'

        $role = Role::find($request->input('role_id')) ?? Role::where('name', 'player')->first();

        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $managerId = $request->input('manager_id');

        if ($role->name == 'player'){
            $manager = $managerId ?? $currentUserId;
        } else {
            $manager = null;
        }

        $user = User::create([
            'manager_id'    => $manager,
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

        //Create Wallet and add Deposit
        $deposit = $this->currencyService->toCents($request->get('deposit', 0));
        $receiverUserId = $user->id;
        $sender_user_id = $currentUserId;
        $currency = config('crm.currency');
        $status = 'approved';

        if($currentUser->hasRole('administrator')){
            $user->deposit($deposit, $receiverUserId, $sender_user_id, $currency, $status);
        } else {
            if(($currentUser->canWithdraw($deposit))){
                $currentUser->wallet->balance -= $deposit;
                $currentUser->wallet->save();
                $user->deposit($deposit, $receiverUserId, $sender_user_id, $currency, $status);
            }
        }

        return redirect()->route('users.index')->with('success', "The user <strong>$user->name</strong> has successfully been created.");
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
        try {
            $user = User::findOrFail($id);
            $params = [
                'title' => 'Confirm Delete Record',
                'user' => $user,
            ];
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return view('admin.users.users_delete')->with($params);
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
            $user = User::findOrFail($id);

            $managers = User::whereHas('roles', function($q){
                $q->where('name', 'manager');
            })->get();

            $params = [
                'title'     => 'Edit User',
                'user'      => $user,
                'roles'     => Role::with('permissions')->pluck('display_name', 'id'),
                'managers'  => $managers,
                'balance'   => $this->currencyService->toDollars($user->balance),
                'countries' => Country::pluck('name', 'id'),
                'languages' => Language::pluck('name', 'id'),
            ];
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return view('admin.users.users_edit')->with($params);
    }

    /**
     * @param Request $request
     * @param int     $id
     *
     * @return RedirectResponse|Response
     * @throws ValidationException
     */
    public function update(Request $request, $id)
    {
        //
        try {
            $user = User::findOrFail($id);
            $this->validate($request, [
                'name'              => 'required|unique:users|min:4|max:20',
                'first_name'        => 'required|max:20',
                'last_name'         => 'required|max:20',
                'email'             => 'required|email|unique:users,email,' . $id,
                'country'           => 'required|integer',
                'language'          => 'required|integer',
                'confirm_password'  => 'same:password',
                'avatar'            => 'image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            $path = $this->imageService->handleUploadedImage($request, $user, '/images/avatar/', 'avatar');

            // If current user is 'administrator' use '$manager_id', if 'manager' use '$current_user_id'
            // Add 'manager' only for 'player'

            $role = Role::find($request->input('role_id')) ?? Role::where('name', 'player')->first();

            $currentUser = Auth::user();
            $currentUserId = $currentUser->id;
            $managerId = $request->input('manager_id');

            if($role->name == 'player'){
                $manager = $managerId ?? $currentUserId;
            }else{
                $manager = null;
            }

            $user->manager_id   = $manager;
            $user->name         = $request->input('name');
            $user->first_name   = $request->input('first_name');
            $user->last_name    = $request->input('last_name');
            $user->phone        = $request->input('phone') ?? '';
            $user->email        = $request->input('email');
            $user->country_id   = $request->input('country');
            $user->language_id  = $request->input('language');

            if(!empty($path)){
                $user->avatar = $path;
            }

            if(!empty($request->input('password'))){
                $user->password = bcrypt($request->input('password'));
            }

            $user->save();

            // Update role of the user
            $roles = $user->roles;
            foreach ($roles as $key => $value) {
                $user->detachRole($value);
            }
            
            $user->attachRole($role);
            // Update permission of the user
            //$permission = Permission::find($request->input('permission_id'));
            //$user->attachPermission($permission);

            //Update Deposit
            $deposit = $this->currencyService->toCents(request()->get('deposit', 0));
            $receiverUserId = $user->id;
            $senderUserId = $currentUserId;
            $currency = config('crm.currency');
            $status = 'approved';

            if($currentUser->hasRole('administrator')){
                $user->deposit($deposit, $receiverUserId, $senderUserId, $currency, $status);
            }else{
                if(($currentUser->canWithdraw($deposit))){
                    $currentUser->wallet->balance -= $deposit;
                    $currentUser->wallet->save();
                    $user->deposit($deposit, $receiverUserId, $senderUserId, $currency, $status);
                }
            }

            //Withdraw
            if($request->input('withdraw')){

                $withdraw = $this->currencyService->toCents($request->input('withdraw'));
                $receiverUserId = $currentUserId;
                $senderUserId = $user->id;

                $currency = config('crm.currency');
                $status = 'approved';

                if ($currentUser->hasRole('administrator')) {
                    $user->withdraw($withdraw, $receiverUserId, $senderUserId, $currency, $status);
                } else {
                    if (($user->canWithdraw($withdraw))) {
                        $user->withdraw($withdraw, $receiverUserId, $senderUserId, $currency, $status);
                        $currentUser->wallet->balance += $withdraw;
                        $currentUser->wallet->save();
                    }
                }
            }
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return redirect()->route('users.index')->with('success', "The user <strong>$user->name</strong> has successfully been updated.");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function destroy($id)
    {
        //
        try {
            $user = User::findOrFail($id);

            $this->imageService->handleDestroyImage($user, 'avatar');

            // Detach from Role
            $roles = $user->roles;
            foreach ($roles as $key => $value) {
                $user->detachRole($value);
            }
            $user->delete();
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return redirect()->route('users.index')->with('success', "The user <strong>$user->name</strong> has successfully been archived.");
    }

    /**
     * Transfer money
     * Sending money from user to another
     *
     * @param Request $request
     */
    public function send(Request $request)
    {
        $ajaxResponse = $request->all();
        $id = $ajaxResponse['user_id'] ?? 0;
        $deposit = $ajaxResponse['deposit'] ?? 0;
        $withdraw = $ajaxResponse['withdraw'] ?? 0;

        $current_user = Auth::user();

        if($id){
            $user = User::findOrFail($id);

            //Update Deposit
            if($deposit){
                $deposit = $this->currencyService->toCents($deposit);
                $receiverUserId = $user->id;
                $senderUserId = $current_user->id;
                $currency = config('crm.currency');
                $status = 'approved';

                if($current_user->hasRole('administrator')){
                    $user->deposit($deposit, $receiverUserId, $senderUserId, $currency, $status);
                }else{
                    if(($current_user->canWithdraw($deposit))){
                        $current_user->wallet->balance -= $deposit;
                        $current_user->wallet->save();
                        $user->deposit($deposit, $receiverUserId, $senderUserId, $currency, $status);
                    }
                }
            }

            //Withdraw
            if($withdraw){
                $withdraw = $this->currencyService->toCents($withdraw);
                $receiverUserId = $current_user->id;
                $senderUserId = $user->id;
                $currency = config('crm.currency');
                $status = 'approved';

                if($current_user->hasRole('administrator')){
                    $user->withdraw($withdraw, $receiverUserId, $senderUserId, $currency, $status);
                }else{
                    if(($user->canWithdraw($withdraw))){
                        $user->withdraw($withdraw, $receiverUserId, $senderUserId, $currency, $status);
                        $current_user->wallet->balance += $withdraw;
                        $current_user->wallet->save();
                    }
                }
            }
        }

    }

    /**
     * Changing user status
     *
     * @param int $id
     *
     * @return RedirectResponse|Response
     */
    public function changeStatus($id)
    {
        $user = User::find($id);
        if ($user == null) {
            return response()->view('errors.' . '404');
        }
        $user->status = ($user->status == 0) ? 1 : 0;
        $user->save();

        return redirect()->route('users.index')->with('success', "Status of the user <strong>$user->name</strong> has been updated.");
    }

    /**
     * @return RedirectResponse
     */
    public function loginAs()
    {
        //Login as section from start page
        $currentUser = Auth::user();
        if (empty($currentUser)) {
            switch (request()->get('role_id')) {
                case 'administrator':
                    $roleId = USER::ROLE_ADMINISTRATOR_ID;
                    break;

                case 'manager':
                    $roleId = USER::ROLE_MANAGER_ID;
                    break;

                default:
                    return redirect()->route('home');
            }

            $user = User::with('roles')
                ->filterByRole($roleId)
                ->first();
            if (!empty($user)) {
                Auth::loginUsingId($user->id);
                return redirect()->route('index');
            }
            return redirect()->route('home');
        }

        //If we want to return to our account
        if (request()->session()->has('originalUser')) {
            Auth::loginUsingId(request()->session()->pull('originalUser')->id, true);
            return redirect()->route('users.index');
        }

        //Get User's ID, we want to login as
        $id = request()->get('user_id');

        //Check user, if he has enough power to login as another user
        if ($currentUser->hasRole('administrator')) {
            request()->session()->put('originalUser', $currentUser);
            Auth::loginUsingId($id);
            return redirect()->route('index');
        }
    }
}
