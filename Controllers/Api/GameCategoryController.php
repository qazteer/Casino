<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\GameCategory as GameCategoryResource;
use App\Models\GameCategory;
use Illuminate\Database\Query\Builder;

class GameCategoryController extends BaseApiController
{
    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return GameCategoryResource::collection(
            GameCategory::whereHas('games', function ($query) {
                /** @var Builder $query */
                return $query->where('active', '=', 1);
            })->get()
        );
    }
}
