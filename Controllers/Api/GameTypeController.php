<?php

namespace App\Http\Controllers\Api;

use App\Models\Game;
use App\Http\Controllers\Controller;

class GameTypeController extends Controller
{
    /**
     * @return string
     */
    public function index()
    {
        $responce['data'] = [];
        foreach (Game::getTypes() as $id => $name) {
            $responce['data'][] = (object) ['id' => $id, 'name' => $name];
        }
        return $responce;
    }
}
