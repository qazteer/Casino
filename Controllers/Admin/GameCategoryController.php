<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageService;
use App\Http\Controllers\Controller;
use App\Models\GameCategory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Exception;

class GameCategoryController extends Controller
{
    /**
     * @var ImageService
     */
    protected $imageService;

    /**
     * GameCategoryController constructor.
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
        //
        $categories = GameCategory::sortable()->paginate(10);
        $params = [
            'title' => 'Categories Listing',
            'categories' => $categories,
        ];

        return view('admin.categories.index')->with($params);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Factory|View
     */
    public function create()
    {
        //
        $params = [
            'title' => 'Create Category',
        ];

        return view('admin.categories.categories_create')->with($params);
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
            'category_icon' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $path = $this->imageService->handleUploadedImage($request, '', '/images/category/', 'category_icon');

        $category = GameCategory::create([
            'name' => $request->input('name'),
            'category_icon' => $path,
        ]);

        return redirect()->route('categories.index')->with('success', "The categoriy <strong>$category->name</strong> has successfully been created.");
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
            $category = GameCategory::findOrFail($id);
            $params = [
                'title' => 'Confirm Delete Record',
                'category' => $category,
            ];
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return view('admin.categories.categories_delete')->with($params);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return RedirectResponse|Response
     */
    public function edit($id)
    {
        //
        try {
            $category = GameCategory::findOrFail($id);

            $params = [
                'title' => 'Edit Category',
                'category' => $category,
            ];
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }
        return view('admin.categories.categories_edit')->with($params);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return RedirectResponse|Response
     * @throws ValidationException
     */
    public function update(Request $request, $id)
    {
        //
        try {
            $category = GameCategory::findOrFail($id);
            $this->validate($request, [
                'name' => 'required',
                'category_icon' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            $path = $this->imageService->handleUploadedImage($request, $category, '/images/category/', 'category_icon');

            $category->name = $request->input('name');
            if (!empty($path)) {
                $category->category_icon = $path;
            }
            $category->save();
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }
        return redirect()->route('categories.index')->with('success', "The category <strong>$category->name</strong> has successfully been updated.");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return RedirectResponse|Response
     */
    public function destroy($id)
    {
        //
        try {
            $category = GameCategory::findOrFail($id);

            $this->imageService->handleDestroyImage($category, 'category_icon');
            
            $category->delete();
        } catch (Exception $ex) {
            if ($ex instanceof Exception) {
                return response()->view('errors.' . '404');
            }
        }
        return redirect()->route('categories.index')->with('success', "The category <strong>$category->name</strong> has successfully been archived.");
    }
}
