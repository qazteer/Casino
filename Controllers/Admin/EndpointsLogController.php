<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndpointsLog;
use App\Models\GameProvider;
use App\Traits\DateRangeTrait;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EndpointsLogController extends Controller
{
    use DateRangeTrait;

    /**
     * @return Factory|View
     */
    public function index()
    {
        $date = $this->dateRange();

        $filters = request()->only([
            'method',
            'provider',
            'type',
            'id_search',
            'url_search',
        ]);

        $logs = EndpointsLog::sorted()
            ->methods()
            ->providers()
            ->types()
            ->searchByUrl()
            ->searchById()
            ->whereBetween('created_at', [$date['start'], $date['end']])
            ->paginate(20);

        $params = [
            'title'     => 'Requests',
            'logs'      => $logs,
            'filters'   => $filters,
            'date'      => $date,
            'methods'   => EndpointsLog::METHODS,
            'providers' => GameProvider::pluck('name'),
            'types'     => EndpointsLog::TYPES,
        ];

        return view('admin.logs.index')->with($params);
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
        $params = [];

        try {
            $log   = EndpointsLog::findOrFail($id);
            $params = [
                'title' => 'Confirm Delete Record',
                'log'   => $log,
            ];
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return view('admin.logs.logs_delete')->with($params);
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
            $log = EndpointsLog::findOrFail($id);

            $log->delete();
        } catch (ModelNotFoundException $ex) {
            if ($ex instanceof ModelNotFoundException) {
                return response()->view('errors.' . '404');
            }
        }

        return redirect()->route('logs.index')->with('success', "The game <strong>$log->id</strong> has successfully been archived.");
    }
}
