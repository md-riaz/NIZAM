<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EslConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

/**
 * Platform admin log viewer for FreeSWITCH and Laravel application logs.
 */
class LogViewerController extends Controller
{
    /**
     * Stream FreeSWITCH logs live via ESL.
     * 
     * Note: This endpoint queries the current log level. For live log streaming,
     * implement SSE/WebSocket with ESL event subscription to 'log' events.
     */
    public function freeswitch(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $level = $request->input('level', 'info');

        $validLevels = ['console', 'alert', 'crit', 'err', 'warning', 'notice', 'info', 'debug'];
        if (! in_array($level, $validLevels)) {
            return response()->json(['error' => 'Invalid log level'], 400);
        }

        try {
            $esl = EslConnectionManager::fromConfig();
            if (! $esl->connect()) {
                return response()->json([
                    'error' => 'Failed to connect to FreeSWITCH ESL',
                    'logs' => [],
                ], 503);
            }

            // Query current log level
            $logLevelResponse = $esl->api("fsctl loglevel");
            
            // Get FreeSWITCH status for context
            $statusResponse = $esl->api("status");
            
            $esl->disconnect();

            return response()->json([
                'source' => 'freeswitch',
                'level' => $level,
                'current_log_level' => trim($logLevelResponse),
                'status' => trim($statusResponse),
                'note' => 'For live log streaming, implement SSE/WebSocket with ESL event subscription to "log" events.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to query FreeSWITCH logs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View Laravel application logs.
     */
    public function application(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $lines = min((int) $request->input('lines', 100), 1000);
        $logFile = storage_path('logs/laravel.log');

        if (! File::exists($logFile)) {
            return response()->json([
                'error' => 'Log file not found',
                'path' => $logFile,
                'logs' => [],
            ], 404);
        }

        try {
            $content = File::get($logFile);
            $allLines = explode("\n", $content);
            $recentLines = array_slice($allLines, -$lines);

            return response()->json([
                'source' => 'laravel',
                'path' => $logFile,
                'lines' => count($recentLines),
                'logs' => $recentLines,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to read log file',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List available log files.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('platform-admin');

        $logDir = storage_path('logs');
        $files = [];

        if (File::isDirectory($logDir)) {
            foreach (File::files($logDir) as $file) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                ];
            }
        }

        return response()->json([
            'directory' => $logDir,
            'files' => $files,
        ]);
    }
}
