<?php

namespace App\Services\Admin;

use App\Services\Media\FreeSwitchCommandService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FreeSwitchModuleStatusService
{
    protected const ALLOWLISTED_UNLOADABLE_MODULES = [
        'mod_avmd',
        'mod_signalwire',
        'mod_xml_curl',
    ];

    protected const KNOWN_PLATFORM_MODULES = [
        'mod_sofia',
        'mod_xml_curl',
        'mod_avmd',
        'mod_signalwire',
    ];

    public function __construct(
        protected ?FreeSwitchCommandService $freeSwitch = null,
    ) {
        $this->freeSwitch ??= app(FreeSwitchCommandService::class);
    }

    /**
     * @return array{ok: bool, data: Collection<int, array{name: string, type: string, status: string, supports_start: bool, supports_stop: bool}>, error: string|null, live: bool, source: string}
     */
    public function list(): array
    {
        $jsonResult = $this->freeSwitch->execute('show', ['modules', 'as', 'json']);

        if ($jsonResult['executed'] ?? false) {
            $rows = $this->parseShowModulesJsonResponse((string) ($jsonResult['response'] ?? ''));

            if ($rows->isNotEmpty()) {
                return [
                    'ok' => true,
                    'data' => $rows,
                    'error' => null,
                    'live' => true,
                    'source' => 'esl',
                ];
            }
        }

        $textResult = $this->freeSwitch->execute('show', ['modules']);

        if (! ($textResult['executed'] ?? false)) {
            return [
                'ok' => false,
                'data' => collect(),
                'error' => (string) ($textResult['error'] ?? $jsonResult['error'] ?? 'FreeSWITCH unreachable'),
                'live' => true,
                'source' => 'esl',
            ];
        }

        return [
            'ok' => true,
            'data' => $this->parseShowModulesOutput($this->extractBody((string) ($textResult['response'] ?? ''))),
            'error' => null,
            'live' => true,
            'source' => 'esl',
        ];
    }

    /**
     * @return array{ok: bool, action: string, module: string, response: string|null, error: string|null}
     */
    public function start(string $module): array
    {
        $module = $this->sanitizeModuleName($module);
        $result = $this->freeSwitch->execute('load', [$module]);

        return [
            'ok' => (bool) ($result['executed'] ?? false),
            'action' => 'start',
            'module' => $module,
            'response' => isset($result['response']) ? (string) $result['response'] : null,
            'error' => ($result['executed'] ?? false) ? null : (string) ($result['error'] ?? 'Unable to load FreeSWITCH module.'),
        ];
    }

    /**
     * @return array{ok: bool, action: string, module: string, response: string|null, error: string|null}
     */
    public function stop(string $module): array
    {
        $module = $this->sanitizeModuleName($module);

        if (! $this->supportsStop($module)) {
            return [
                'ok' => false,
                'action' => 'stop',
                'module' => $module,
                'response' => null,
                'error' => 'This module cannot be stopped from the platform admin UI.',
            ];
        }

        $result = $this->freeSwitch->execute('unload', [$module]);

        return [
            'ok' => (bool) ($result['executed'] ?? false),
            'action' => 'stop',
            'module' => $module,
            'response' => isset($result['response']) ? (string) ($result['response']) : null,
            'error' => ($result['executed'] ?? false) ? null : (string) ($result['error'] ?? 'Unable to unload FreeSWITCH module.'),
        ];
    }

    public function parseShowModulesJsonResponse(string $response): Collection
    {
        return $this->normalizeModuleRows($this->parseJsonRows($response));
    }

    public function parseShowModulesOutput(string $output): Collection
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_contains($line, ':') || str_ends_with($line, ' total.')) {
                continue;
            }

            [$type, $name, $ikey, $filename] = array_pad(str_getcsv($line), 4, '');

            if ($type === 'type' && $name === 'name' && $ikey === 'ikey') {
                continue;
            }

            if ($ikey === '' || ! str_starts_with($ikey, 'mod_')) {
                continue;
            }

            $rows[] = [
                'type' => trim($type),
                'name' => trim($name),
                'ikey' => trim($ikey),
                'filename' => trim($filename),
            ];
        }

        return $this->normalizeModuleRows($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return Collection<int, array{name: string, type: string, status: string, supports_start: bool, supports_stop: bool}>
     */
    protected function normalizeModuleRows(array $rows): Collection
    {
        $loaded = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): ?array {
                $module = trim((string) ($row['ikey'] ?? $row['module'] ?? ''));
                if ($module === '' || ! str_starts_with($module, 'mod_')) {
                    return null;
                }

                return [
                    'module' => $module,
                    'type' => trim((string) ($row['type'] ?? 'module')),
                ];
            })
            ->filter()
            ->groupBy('module');

        $moduleNames = collect(array_merge(self::KNOWN_PLATFORM_MODULES, $loaded->keys()->all()))
            ->unique()
            ->sort()
            ->values();

        return $moduleNames->map(function (string $module) use ($loaded): array {
            $group = $loaded->get($module, collect());
            $type = (string) ($group->pluck('type')->filter()->first() ?? 'module');
            $status = $group->isNotEmpty() ? 'running' : 'not_loaded';

            return $this->normalizeRow($module, $type, $status);
        })->values();
    }

    public function supportsStop(string $module): bool
    {
        return in_array($module, self::ALLOWLISTED_UNLOADABLE_MODULES, true);
    }

    protected function normalizeRow(string $name, string $type, string $status): array
    {
        $normalizedStatus = Str::of(trim($status))->lower()->replace(' ', '_')->value();

        return [
            'name' => $name,
            'type' => $type,
            'status' => $normalizedStatus,
            'supports_start' => $normalizedStatus !== 'running',
            'supports_stop' => $normalizedStatus === 'running' && $this->supportsStop($name),
        ];
    }

    protected function parseJsonRows(string $raw): array
    {
        $jsonStart = strpos($raw, '{');

        if ($jsonStart === false) {
            return [];
        }

        $decoded = json_decode(substr($raw, $jsonStart), true);

        if (! is_array($decoded)) {
            return [];
        }

        $rows = $decoded['rows'] ?? $decoded['modules'] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    protected function sanitizeModuleName(string $module): string
    {
        $module = trim($module);

        if ($module === '' || ! preg_match('/^mod_[A-Za-z0-9_]+$/', $module)) {
            throw new \InvalidArgumentException('A valid FreeSWITCH module name is required.');
        }

        return $module;
    }

    protected function extractBody(string $response): string
    {
        $parts = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);

        return $parts[1] ?? $response;
    }
}
