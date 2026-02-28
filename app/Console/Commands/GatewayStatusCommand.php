<?php

namespace App\Console\Commands;

use App\Services\EslConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GatewayStatusCommand extends Command
{
    protected $signature = 'nizam:gateway-status {--interval=60 : Polling interval in seconds}';

    protected $description = 'Poll FreeSWITCH gateway status and cache the results';

    public function handle(): int
    {
        $interval = (int) $this->option('interval');

        $this->info('Polling FreeSWITCH gateway status...');

        $esl = EslConnectionManager::fromConfig();

        if (! $esl->connect()) {
            $this->error('Failed to connect to FreeSWITCH ESL.');
            Log::error('Gateway status: ESL connection failed');
            Cache::put('nizam:gateway_status', [
                'status' => 'unreachable',
                'checked_at' => now()->toIso8601String(),
            ], $interval * 2);

            return self::FAILURE;
        }

        $sofiaStatus = $esl->api('sofia status');
        $gatewayData = $this->parseSofiaStatus($sofiaStatus);

        $registrations = $esl->api('show registrations as json');
        $registrationData = $this->parseRegistrations($registrations);

        $esl->disconnect();

        $status = [
            'status' => 'ok',
            'gateways' => $gatewayData,
            'registrations' => $registrationData,
            'checked_at' => now()->toIso8601String(),
        ];

        Cache::put('nizam:gateway_status', $status, $interval * 2);

        $this->info('Gateway status updated.');
        $this->table(
            ['Name', 'Type', 'Status'],
            collect($gatewayData)->map(fn ($gw) => [$gw['name'], $gw['type'], $gw['status']])->toArray()
        );

        return self::SUCCESS;
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

        $data = json_decode($response, true);

        return [
            'count' => $data['row_count'] ?? 0,
            'entries' => $data['rows'] ?? [],
        ];
    }
}
