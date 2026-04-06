<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

/**
 * Platform admin log viewer for FreeSWITCH and Laravel application logs.
 */
class LogViewerController extends Controller
{
    public function freeswitch(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $fileSizeKb = min(max((int) $request->integer('size_kb', 256), 32), 4096);
        $filter = trim((string) $request->input('filter', ''));
        $sort = strtolower((string) $request->input('sort', 'desc'));
        $sort = in_array($sort, ['asc', 'desc'], true) ? $sort : 'desc';
        $logFile = config('telephony.freeswitch.log_path');

        if (! $logFile || ! File::exists($logFile)) {
            return response()->json([
                'error' => 'Log file not found',
                'path' => $logFile,
                'logs' => [],
            ], 404);
        }

        try {
            $content = $this->readTrailingBytes($logFile, $fileSizeKb * 1024);
            $allLines = preg_split("/\r\n|\n|\r/", $content) ?: [];
            $allLines = array_values(array_filter($allLines, static fn (string $line): bool => $line !== ''));

            $offset = $this->estimateStartingLineNumber($logFile, strlen($content), count($allLines));
            $logs = [];

            foreach ($allLines as $index => $line) {
                if ($filter !== '' && stripos($line, $filter) === false) {
                    continue;
                }

                $logs[] = [
                    'number' => $offset + $index,
                    'text' => $line,
                ];
            }

            if ($sort === 'desc') {
                $logs = array_reverse($logs);
            }

            return response()->json([
                'source' => 'freeswitch',
                'path' => $logFile,
                'size_kb' => $fileSizeKb,
                'filter' => $filter,
                'sort' => $sort,
                'lines' => count($logs),
                'logs' => $logs,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to read log file',
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
                    'type' => 'laravel',
                ];
            }
        }

        // Include FreeSWITCH log if configured
        $fsLogFile = config('telephony.freeswitch.log_path');
        if ($fsLogFile && File::exists($fsLogFile)) {
            $files[] = [
                'name' => basename($fsLogFile),
                'path' => $fsLogFile,
                'size' => File::size($fsLogFile),
                'modified' => File::lastModified($fsLogFile),
                'type' => 'freeswitch',
            ];
        }

        return response()->json([
            'directory' => $logDir,
            'files' => $files,
        ]);
    }

    private function readTrailingBytes(string $path, int $bytes): string
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open log file.');
        }

        try {
            $fileSize = filesize($path);
            if ($fileSize === false || $fileSize === 0) {
                return '';
            }

            $start = max(0, $fileSize - $bytes);
            fseek($handle, $start);
            $content = stream_get_contents($handle) ?: '';

            if ($start > 0) {
                $newlinePos = strpos($content, "\n");
                if ($newlinePos !== false) {
                    $content = substr($content, $newlinePos + 1);
                }
            }

            return $content;
        } finally {
            fclose($handle);
        }
    }

    private function estimateStartingLineNumber(string $path, int $chunkLength, int $lineCount): int
    {
        $totalSize = filesize($path);
        if ($totalSize === false || $totalSize <= $chunkLength || $lineCount === 0) {
            return 1;
        }

        $prefixLength = $totalSize - $chunkLength;
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 1;
        }

        try {
            $prefix = $prefixLength > 0 ? fread($handle, $prefixLength) : '';
            if (! is_string($prefix) || $prefix === '') {
                return 1;
            }

            return substr_count($prefix, "\n") + 1;
        } finally {
            fclose($handle);
        }
    }
}
