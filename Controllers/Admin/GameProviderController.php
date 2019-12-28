<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Exception;

class GameProviderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
        $providers = GameProvider::sortable()->paginate(10);
        $params = [
            'title' => 'Providers Listing',
            'providers' => $providers,
        ];
        return view('admin.providers.index')->with($params);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
        $params = [
            'title' => 'Create Provider',
        ];
        return view('admin.providers.providers_create')->with($params);
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
            'name' => 'required',
        ]);

        $provider = GameProvider::create([
            'name' => $request->input('name'),
        ]);

        return redirect()->route('providers.index')->with('success', "The provider <strong>$provider->name</strong> has successfully been created.");
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
            $provider = GameProvider::findOrFail($id);
            $params = [
                'title' => 'Confirm Delete Record',
                'provider' => $provider,
            ];
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return view('admin.providers.providers_delete')->with($params);
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
            $provider = GameProvider::findOrFail($id);

            $params = [
                'title' => 'Edit Provider',
                'provider' => $provider,
            ];
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return view('admin.providers.providers_edit')->with($params);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse|Response
     * @throws ValidationException
     */
    public function update(Request $request, $id)
    {
        //
        try {
            $provider = GameProvider::findOrFail($id);
            $this->validate($request, [
                'name' => 'required',
            ]);

            $provider->name = $request->input('name');
            $provider->save();
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return redirect()->route('providers.index')->with('success', "The provider <strong>$provider->name</strong> has successfully been updated.");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    /**
     * @param $id
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function destroy($id)
    {
        //
        try {
            $provider = GameProvider::findOrFail($id);
            
            $provider->delete();
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return redirect()->route('providers.index')->with('success', "The provider <strong>$provider->name</strong> has successfully been archived.");
    }
}
