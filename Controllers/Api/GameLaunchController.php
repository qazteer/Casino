<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Services\Contracts\GameLaunchServiceInterface;

class GameLaunchController extends Controller
{
    /**
     * @param GameLaunchServiceInterface $gameLaunchService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function launch(GameLaunchServiceInterface $gameLaunchService)
    {
        $data = $gameLaunchService->launch();

        return $data;
    }
}