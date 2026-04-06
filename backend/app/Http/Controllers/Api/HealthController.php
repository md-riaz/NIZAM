<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EslConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Return health status for the platform including FreeSWITCH, database, and cache connectivity.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = $this->checkDatabase();
        $cacheStatus = $this->checkRedis();
        $switchStatus = $this->checkFreeSwitch();

        $healthy = $dbStatus['status'] === 'ok'
            && $cacheStatus['status'] === 'ok'
            && $switchStatus['status'] === 'ok';

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => [
                'app' => ['status' => 'ok'],
                'database' => $dbStatus,
                'cache' => $cacheStatus,
                'esl' => [
                    'status' => $switchStatus['esl_status'],
                    'connected' => $switchStatus['connected'],
                ],
                'freeswitch' => $switchStatus['freeswitch'],
                'gateways' => $switchStatus['gateways'],
                'registrations' => $switchStatus['registrations'],
            ],
        ], $healthy ? 200 : 503);
    }

    protected function checkDatabase(): array
    {
        try {
            DB::selectOne('SELECT 1');

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkRedis(): array
    {
        try {
            // Use the configured cache store so the check works in all environments
            // (array in tests, redis in production).
            Cache::store()->put('nizam:health_probe', 1, 5);

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkFreeSwitch(): array
    {
        try {
            $esl = EslConnectionManager::fromConfig();
            $connected = $esl->connect();

            if (! $connected) {
                return [
                    'status' => 'unreachable',
                    'esl_status' => 'unreachable',
                    'connected' => false,
                    'freeswitch' => ['raw' => null],
                    'gateways' => [
                        'status' => 'unreachable',
                        'entries' => [],
                        'checked_at' => now()->toIso8601String(),
                        'source' => 'esl',
                        'live' => true,
                    ],
                    'registrations' => [
                        'status' => 'unreachable',
                        'count' => 0,
                        'entries' => [],
                        'checked_at' => now()->toIso8601String(),
                        'source' => 'esl',
                        'live' => true,
                    ],
                ];
            }

            $statusResponse = $esl->api('status');
            $gatewayResponse = $esl->api('sofia status');
            $registrationsResponse = $esl->api('show registrations as json');
            $esl->disconnect();

            $registrations = $this->parseRegistrations($registrationsResponse);

            return [
                'status' => 'ok',
                'esl_status' => 'ok',
                'connected' => true,
                'freeswitch' => $this->parseFreeswitchStatus($statusResponse),
                'gateways' => [
                    'status' => 'ok',
                    'entries' => $this->parseSofiaStatus($gatewayResponse),
                    'checked_at' => now()->toIso8601String(),
                    'source' => 'esl',
                    'live' => true,
                ],
                'registrations' => [
                    'status' => 'ok',
                    'count' => $registrations['count'],
                    'entries' => $registrations['entries'],
                    'checked_at' => now()->toIso8601String(),
                    'source' => 'esl',
                    'live' => true,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'esl_status' => 'error',
                'connected' => false,
                'message' => $e->getMessage(),
                'freeswitch' => ['raw' => null],
                'gateways' => [
                    'status' => 'error',
                    'entries' => [],
                    'checked_at' => now()->toIso8601String(),
                    'source' => 'esl',
                    'live' => true,
                ],
                'registrations' => [
                    'status' => 'error',
                    'count' => 0,
                    'entries' => [],
                    'checked_at' => now()->toIso8601String(),
                    'source' => 'esl',
                    'live' => true,
                ],
            ];
        }
    }

    protected function parseFreeswitchStatus(?string $response): array
    {
        if (! $response) {
            return ['raw' => null];
        }

        $data = ['raw' => trim($response)];

        if (preg_match('/UP (\d+) years?,\s*(\d+) days?/i', $response, $matches)) {
            $data['uptime'] = "{$matches[1]}y {$matches[2]}d";
        } elseif (preg_match('/UP (\d+) days?/i', $response, $matches)) {
            $data['uptime'] = "{$matches[1]}d";
        }

        if (preg_match('/(\d+) session\(s\)/i', $response, $matches)) {
            $data['sessions'] = (int) $matches[1];
        }

        return $data;
    }

    protected function parseSofiaStatus(?string $response): array
    {
        if (! $response) {
            return [];
        }

        $gateways = [];
        $lines = explode("\n", trim($response));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '=') || str_starts_with($line, 'Name')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 4) {
                $gateways[] = [
                    'name' => $parts[0],
                    'type' => $parts[1] ?? 'unknown',
                    'status' => $parts[3] ?? 'unknown',
                ];
            }
        }

        return $gateways;
    }

    protected function parseRegistrations(?string $response): array
    {
        if (! $response) {
            return ['count' => 0, 'entries' => []];
        }

        $jsonStart = strpos($response, '{');
        if ($jsonStart !== false) {
            $response = substr($response, $jsonStart);
        }

        $data = json_decode($response, true);

        return [
            'count' => $data['row_count'] ?? 0,
            'entries' => $data['rows'] ?? [],
        ];
    }
}
