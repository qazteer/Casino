<?php

namespace App\Http\Controllers\Api;

use App\Models\Game;
use App\Http\Resources\GameCollection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;

class GameController extends Controller
{
    /**
     * @return GameCollection
     */
    public function index()
    {
        $providerFilter = request()->get('provider', false);
        $categoryFilter = request()->get('category', false);
        $typeFilter = request()->get('type', false);
        $searchString = request()->get('searchString', false);

        return new GameCollection(
            Game::query()->active()->orderByFeatured()
            ->when($providerFilter, function ($collection) use ($providerFilter) {
                /** @var Collection $collection */
                return $collection->where('provider_id', $providerFilter);
            })->when($categoryFilter, function ($collection) use ($categoryFilter) {
                /** @var Collection $collection */
                return $collection->whereHas('categories', function($query) use ($categoryFilter) {
                    $query->where('id', $categoryFilter);
                });
            })->when($typeFilter, function ($collection) use ($typeFilter) {
                /** @var Collection $collection */
                return $collection->where('type', $typeFilter);
            })->when($searchString, function ($collection) use ($searchString) {
                /** @var Collection $collection */
                return $collection->where('name', 'LIKE', '%' . $searchString . '%');
            })->get()
        );
    }
}
