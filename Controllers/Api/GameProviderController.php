<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\GameProvider as GameProviderResource;
use App\Models\GameProvider;
use App\Http\Controllers\Controller;

class GameProviderController extends Controller
{
    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return GameProviderResource::collection(
            GameProvider::whereHas('games', function ($query) {
                return $query->where('active', '=', 1);
            })->get()
        );
    }
}
