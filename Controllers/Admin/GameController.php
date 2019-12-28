<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageService;
use App\Http\Controllers\Controller;
use App\Models\GameCategory;
use App\Models\GameProvider;
use App\Models\Game;
use App\Services\Contracts\GamesImportServiceInterface;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Exception;

class GameController extends Controller
{
    /**
     * @var ImageService
     */
    protected $imageService;

    /**
     * GameController constructor.
     */
    public function __construct()
    {
        $this->imageService = new ImageService();
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $categories = GameCategory::pluck('name', 'id');
        $types = Game::getTypes();
        $providers = GameProvider::pluck('name', 'id');

        $filters = request()->only([
            'code',
            'category',
            'type',
            'provider',
            'status'
        ]);

        $games = Game::with('categories', 'provider')
            ->filterCategories()
            ->types()
            ->providers()
            ->status()
            ->search()
            ->sorted()
            ->paginate(10);

        $params = [
            'title' => 'Games Listing',
            'games' => $games,
            'categories' => $categories,
            'types' => $types,
            'providers' => $providers,
            'filters' => $filters,
            'status' => Game::STATUS,
        ];

        return view('admin.games.index')->with($params);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
        $categories = GameCategory::pluck('name', 'id');
        $providers  = GameProvider::pluck('name', 'id');
        $types      = Game::getTypes();
        $params     = [
            'title'      => 'Create Game',
            'categories' => $categories,
            'types'      => $types,
            'providers'  => $providers,
        ];

        return view('admin.games.games_create')->with($params);
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $types = Game::getTypes();
        $this->validate($request, [
            'name'        => 'required',
            'code'        => 'required',
            'categories'  => 'required',
            'provider_id' => 'required',
            'image'       => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $path = $this->imageService->handleUploadedImage($request);

        $game = Game::create([
            'name'        => $request->input('name'),
            'code'        => $request->input('code'),
            'code_mobile' => !empty($request->input('code_mobile')) ? $request->input('code_mobile') : '',
            'type'        => array_key_exists($request->input('type'), $types) ? $types[$request->input('type')] : '',
            'provider_id' => $request->input('provider_id'),
            'image'       => $path,
            'is_featured' => $request->input('is_featured') ? 1 : 0,
            'active'      => $request->input('active') ? 1 : 0,
            'params'      => '{}',
        ]);

        $category = GameCategory::find($request->input('categories', []));
        $game->categories()->attach($category);

        return redirect()->route('games.index')->with('success', "The game <strong>$game->name</strong> has successfully been created.");

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id)
    {
        try {
            $game   = Game::findOrFail($id);
            $params = [
                'title' => 'Confirm Delete Record',
                'game'  => $game,
            ];
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return view('admin.games.games_delete')->with($params);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        try {
            $categories = GameCategory::pluck('name', 'id');
            $providers  = GameProvider::pluck('name', 'id');
            $types      = Game::getTypes();
            $game       = Game::with('categories')->findOrFail($id);

            $params = [
                'title'       => 'Edit Game',
                'game'        => $game,
                'categories'  => $categories,
                'types'       => $types,
                'providers'   => $providers,
                'is_featured' => $game->is_featured ? 'checked' : '',
                'active'      => $game->active ? 'checked' : '',
            ];

        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return view('admin.games.games_edit')->with($params);
    }

    /**
     * @param Request $request
     * @param         $id
     *
     * @return RedirectResponse|Response
     * @throws ValidationException
     */
    public function update(Request $request, $id)
    {

        try {
            $game  = Game::findOrFail($id);

            $game->categories()->detach();
            $types = Game::getTypes();
            $this->validate($request, [
                'name'        => 'required',
                'code'        => 'required',
                'categories'  => 'required',
                'provider_id' => 'required',
                'image'       => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $path = $this->imageService->handleUploadedImage($request, $game);

            if (!empty($path)) {
                $game->image = $path;
            }

            $game->name        = $request->input('name');
            $game->code        = $request->input('code');
            $game->code_mobile = !empty($request->input('code_mobile')) ? $request->input('code_mobile') : '';
            $game->type        = (array_key_exists($request->input('type'), $types)) ? $types[$request->input('type')] : '';
            $game->provider_id = $request->input('provider_id');
            $game->is_featured = $request->input('is_featured') ? 1 : 0;
            $game->active      = $request->input('active') ? 1 : 0;
            $game->save();

            $category = GameCategory::find($request->input('categories', []));
            $game->categories()->attach($category);
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return redirect()->route('games.index')->with('success', "The game <strong>$game->name</strong> has successfully been updated.");
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
        try {
            $game = Game::findOrFail($id);

            $game->categories()->detach();

            $this->imageService->handleDestroyImage($game);

            $game->delete();

        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return redirect()->route('games.index')->with('success', "The game <strong>$game->name</strong> has successfully been archived.");
    }

    /**
     * @param GamesImportServiceInterface $gamesImportService
     *
     * @return RedirectResponse
     */
    public function import(GamesImportServiceInterface $gamesImportService)
    {
        $count = $gamesImportService->import();

        return redirect()->route('games.index')->with('success', "{$count} Games imported/updated! ");
    }

    /**
     * @return Factory|View
     */
    public function uploadimages()
    {
        //
        $params = [
            'title' => 'Upload images',
        ];
        return view('admin.games.uploadimages')->with($params);
    }

    /**
     * Upload bulk images.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function upload(Request $request)
    {
        //
        $this->validate($request, [
            'images' => 'required',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        $data = $this->imageService->handleBulkUploadedImages($request, '', '/images/games/', 'images');

        // Update image fields
        $g = new Game();
        $update_data = $g->getUpdateData($data);
        
        DB::update("UPDATE `games` SET `image` = CASE `code` {$update_data['cases']} END WHERE `code` in ({$update_data['codes']})", $update_data['params']);

        return redirect()->route('uploadimages')->with('success', "The images has successfully been uploaded.");
    }
}
