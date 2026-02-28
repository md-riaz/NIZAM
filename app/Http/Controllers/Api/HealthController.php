<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EslConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Return health status for the platform including FreeSWITCH and ESL connectivity.
     */
    public function __invoke(): JsonResponse
    {
        $eslStatus = $this->checkEslConnection();
        $gatewayStatus = $this->getGatewayStatus();

        $healthy = $eslStatus['connected'];

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => [
                'app' => ['status' => 'ok'],
                'esl' => $eslStatus,
                'gateways' => $gatewayStatus,
            ],
        ], $healthy ? 200 : 503);
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
