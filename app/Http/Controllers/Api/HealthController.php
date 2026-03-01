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
        $redisStatus = $this->checkRedis();
        $eslStatus = $this->checkEslConnection();
        $gatewayStatus = $this->getGatewayStatus();

        // The platform is healthy only when the critical backing services are reachable.
        $healthy = $dbStatus['status'] === 'ok' && $redisStatus['status'] === 'ok';

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => [
                'app' => ['status' => 'ok'],
                'database' => $dbStatus,
                'cache' => $redisStatus,
                'esl' => $eslStatus,
                'gateways' => $gatewayStatus,
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

    protected function checkEslConnection(): array
    {
        try {
            $esl = EslConnectionManager::fromConfig();
            $connected = $esl->connect();

            if ($connected) {
                $statusResponse = $esl->api('status');
                $esl->disconnect();

                return [
                    'connected' => true,
                    'status' => 'ok',
                    'freeswitch' => $this->parseFreeswitchStatus($statusResponse),
                ];
            }

            return ['connected' => false, 'status' => 'unreachable'];
        } catch (\Throwable $e) {
            return ['connected' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function getGatewayStatus(): array
    {
        return Cache::get('nizam:gateway_status', [
            'status' => 'unknown',
            'gateways' => [],
            'registrations' => ['count' => 0, 'entries' => []],
            'checked_at' => null,
        ]);
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
}
