<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\FreeSwitchModuleStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class FreeSwitchModuleStatusController extends Controller
{
    public function index(FreeSwitchModuleStatusService $service): JsonResponse
    {
        Gate::authorize('platform-admin');

        $result = $service->list();

        if (! $result['ok']) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'source' => $result['source'],
                    'live' => $result['live'],
                    'error' => $result['error'],
                ],
            ], 503);
        }

        return response()->json([
            'data' => $result['data']->values()->all(),
            'meta' => [
                'source' => $result['source'],
                'live' => $result['live'],
            ],
        ]);
    }

    public function start(Request $request, FreeSwitchModuleStatusService $service): JsonResponse
    {
        Gate::authorize('platform-admin');

        try {
            $result = $service->start((string) $request->input('module'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['error'],
                'meta' => [
                    'module' => $result['module'],
                    'action' => $result['action'],
                ],
            ], 503);
        }

        return response()->json([
            'message' => "FreeSWITCH module {$result['module']} started.",
            'meta' => [
                'module' => $result['module'],
                'action' => $result['action'],
            ],
        ]);
    }

    public function stop(Request $request, FreeSwitchModuleStatusService $service): JsonResponse
    {
        Gate::authorize('platform-admin');

        try {
            $result = $service->stop((string) $request->input('module'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if (! $result['ok']) {
            $status = $result['error'] === 'This module cannot be stopped from the platform admin UI.' ? 422 : 503;

            return response()->json([
                'message' => $result['error'],
                'meta' => [
                    'module' => $result['module'],
                    'action' => $result['action'],
                ],
            ], $status);
        }

        return response()->json([
            'message' => "FreeSWITCH module {$result['module']} stopped.",
            'meta' => [
                'module' => $result['module'],
                'action' => $result['action'],
            ],
        ]);
    }
}
