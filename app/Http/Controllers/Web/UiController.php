<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Alert;
use App\Models\CallDetailRecord;
use App\Models\CallEventLog;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Ivr;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\QueueMetric;
use App\Models\Recording;
use App\Models\RingGroup;
use App\Models\Tenant;
use App\Models\TimeCondition;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use App\Modules\ModuleRegistry;
use App\Services\EslConnectionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Nwidart\Modules\Facades\Module;

class UiController extends Controller
{
    public function dashboard(Request $request, ?Tenant $tenant = null): View
    {
        $tenant = $this->resolveTenant($request, $tenant);
        $systemHealth = $this->systemHealthChecks($tenant);

        return view('ui.dashboard.index', [
            'ui' => $this->uiContext($request, $tenant),
            'metrics' => $this->dashboardMetrics($tenant),
            'systemHealth' => $systemHealth,
        ]);
    }

    public function systemHealth(Request $request, ?Tenant $tenant = null): View
    {
        $tenant = $this->resolveTenant($request, $tenant);
        $systemHealth = $this->systemHealthChecks($tenant);

        return view('ui.dashboard.health', [
            'ui' => $this->uiContext($request, $tenant),
            'health' => $systemHealth['checks'],
            'healthSummary' => $systemHealth['summary'],
        ]);
    }

    public function extensions(Request $request, Tenant $tenant): View
    {
        Gate::authorize('viewAny', Extension::class);

        return view('ui.extensions.index', [
            'ui' => $this->uiContext($request, $tenant),
            'extensions' => Extension::query()->where('tenant_id', $tenant->id)->latest()->get(),
        ]);
    }

    public function extensionStore(Request $request, Tenant $tenant): View
    {
        Gate::authorize('create', Extension::class);

        Extension::query()->create([
            'tenant_id' => $tenant->id,
            ...$request->validate([
                'extension' => ['required', 'string', 'max:20'],
                'password' => ['required', 'string', 'min:4'],
                'directory_first_name' => ['required', 'string', 'max:100'],
                'directory_last_name' => ['required', 'string', 'max:100'],
            ]),
            'voicemail_enabled' => false,
            'is_active' => true,
        ]);

        return $this->extensionTable($tenant);
    }

    public function extensionUpdate(Request $request, Tenant $tenant, Extension $extension): View
    {
        Gate::authorize('update', $extension);

        abort_if($extension->tenant_id !== $tenant->id, 404);

        $data = $request->validate([
            'directory_first_name' => ['required', 'string', 'max:100'],
            'directory_last_name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $extension->update($data);

        return $this->extensionTable($tenant);
    }

    public function extensionDestroy(Tenant $tenant, Extension $extension): View
    {
        Gate::authorize('delete', $extension);

        abort_if($extension->tenant_id !== $tenant->id, 404);
        $extension->delete();

        return $this->extensionTable($tenant);
    }

    public function modulePanel(Request $request, ?Tenant $tenant = null): View
    {
        $tenant = $this->resolveTenant($request, $tenant);

        return view('ui.modules.index', [
            'ui' => $this->uiContext($request, $tenant),
            'modules' => collect(Module::all())
                ->map(fn ($module) => [
                    'name' => $module->getName(),
                    'alias' => (string) $module->get('alias'),
                    'enabled' => $module->isEnabled(),
                    'registry_enabled' => app(ModuleRegistry::class)->isEnabled((string) $module->get('alias')),
                ])
                ->values(),
        ]);
    }

    public function moduleToggle(Request $request, string $moduleName): View|RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        $module = Module::find($moduleName);
        abort_if(! $module, 404);

        Artisan::call($module->isEnabled() ? 'module:disable' : 'module:enable', [
            'module' => $moduleName,
        ]);

        if (! $request->headers->has('HX-Request')) {
            return redirect()->route('ui.modules');
        }

        return view('ui.modules._row', [
            'module' => [
                'name' => $moduleName,
                'alias' => (string) $module->get('alias'),
                'enabled' => Module::find($moduleName)?->isEnabled() ?? false,
                'registry_enabled' => app(ModuleRegistry::class)->isEnabled((string) $module->get('alias')),
            ],
        ]);
    }

    public function surfacePage(Request $request, string $page): View
    {
        $tenant = $this->resolveTenant($request, null);
        $pageConfig = $this->surfacePages()[$page] ?? null;

        abort_if(! $pageConfig, 404);
        abort_if(($pageConfig['admin_only'] ?? false) && ! $request->user()?->isAdmin(), 403);

        return view('ui.surface.index', [
            'ui' => $this->uiContext($request, $tenant),
            'page' => $pageConfig,
            'data' => $this->surfacePageData($page, $tenant),
        ]);
    }

    private function extensionTable(Tenant $tenant): View
    {
        return view('ui.extensions._table', [
            'tenant' => $tenant,
            'extensions' => Extension::query()->where('tenant_id', $tenant->id)->latest()->get(),
        ]);
    }

    private function resolveTenant(Request $request, ?Tenant $tenant): Tenant
    {
        $user = $request->user();
        $requestedTenant = $request->query('tenant');

        if ($user && ! $user->isAdmin()) {
            if ($tenant && $tenant->id !== $user->tenant_id) {
                abort(403);
            }

            $resolvedTenant = Tenant::query()->findOrFail($user->tenant_id);
            abort_unless($resolvedTenant->isOperational(), 403);

            return $resolvedTenant;
        }

        if (! $tenant && $requestedTenant) {
            $tenant = Tenant::query()->find($requestedTenant);
        }

        if ($tenant) {
            return $tenant;
        }

        return Tenant::query()->orderBy('name')->firstOrFail();
    }

    private function dashboardMetrics(Tenant $tenant): array
    {
        return [
            'active_calls' => CallDetailRecord::query()->where('tenant_id', $tenant->id)->whereNull('end_stamp')->count(),
            'waiting_calls' => QueueEntry::query()->where('tenant_id', $tenant->id)->where('status', QueueEntry::STATUS_WAITING)->count(),
            'available_agents' => Agent::query()->where('tenant_id', $tenant->id)->where('state', Agent::STATE_AVAILABLE)->where('is_active', true)->count(),
            'sla_percent' => round((float) QueueMetric::query()->where('tenant_id', $tenant->id)->avg('service_level'), 2),
            'gateway_status' => $this->dashboardGatewayStatus($tenant),
            'webhook_health' => WebhookDeliveryAttempt::query()->whereHas('webhook', fn ($query) => $query->where('tenant_id', $tenant->id))->where('success', false)->exists() ? 'degraded' : 'healthy',
        ];
    }

    private function dashboardGatewayStatus(Tenant $tenant): string
    {
        $activeGateways = Gateway::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->count();

        if ($activeGateways === 0) {
            return 'not configured';
        }

        $cached = Cache::get('nizam:gateway_status', [
            'status' => 'unknown',
            'registrations' => ['count' => 0],
        ]);

        $registered = (int) data_get($cached, 'registrations.count', 0);

        return $registered > 0 ? 'up' : 'down';
    }

    private function systemHealthChecks(Tenant $tenant): array
    {
        $checks = [
            'app' => [
                'label' => 'Application',
                'status' => 'ok',
                'detail' => sprintf('APP_ENV=%s', (string) config('app.env')),
                'meta' => [
                    'url' => (string) config('app.url'),
                ],
            ],
            'database' => $this->databaseHealthCheck(),
            'redis' => $this->redisHealthCheck(),
            'freeswitch' => $this->freeswitchHealthCheck(),
            'gateways' => $this->gatewayHealthCheck($tenant),
            'queue' => $this->queueHealthCheck(),
            'webhooks' => $this->webhookHealthCheck($tenant),
        ];

        $summaryStatus = collect($checks)->contains(fn (array $check) => $check['status'] === 'error')
            ? 'degraded'
            : 'healthy';

        return [
            'summary' => [
                'status' => $summaryStatus,
                'checked_at' => now(),
            ],
            'checks' => $checks,
        ];
    }

    private function databaseHealthCheck(): array
    {
        try {
            DB::selectOne('SELECT 1');

            return [
                'label' => 'Database',
                'status' => 'ok',
                'detail' => 'SELECT 1 ok',
                'meta' => [
                    'connection' => DB::getDefaultConnection(),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Database',
                'status' => 'error',
                'detail' => 'Connection failed',
                'meta' => [
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    private function redisHealthCheck(): array
    {
        try {
            Cache::store()->put('nizam:health_probe', 1, 5);

            return [
                'label' => 'Redis / Cache',
                'status' => 'ok',
                'detail' => 'Cache probe ok',
                'meta' => [
                    'store' => (string) config('cache.default'),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Redis / Cache',
                'status' => 'error',
                'detail' => 'Cache probe failed',
                'meta' => [
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    private function freeswitchHealthCheck(): array
    {
        try {
            $esl = EslConnectionManager::fromConfig();
            $connected = $esl->connect();

            if (! $connected) {
                return [
                    'label' => 'FreeSWITCH ESL',
                    'status' => 'error',
                    'detail' => 'ESL unreachable',
                    'meta' => [
                        'host' => (string) config('nizam.freeswitch.esl_host'),
                        'port' => (string) config('nizam.freeswitch.esl_port'),
                    ],
                ];
            }

            $statusResponse = $esl->api('status');
            $esl->disconnect();
            $parsed = $this->parseFreeswitchStatus($statusResponse);

            return [
                'label' => 'FreeSWITCH ESL',
                'status' => 'ok',
                'detail' => 'Connected'.(isset($parsed['uptime']) ? ', uptime '.$parsed['uptime'] : ''),
                'meta' => [
                    'sessions' => $parsed['sessions'] ?? null,
                    'uptime' => $parsed['uptime'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'FreeSWITCH ESL',
                'status' => 'error',
                'detail' => 'ESL check failed',
                'meta' => [
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    private function gatewayHealthCheck(Tenant $tenant): array
    {
        $cached = Cache::get('nizam:gateway_status', [
            'status' => 'unknown',
            'registrations' => ['count' => 0, 'entries' => []],
            'checked_at' => null,
        ]);

        $activeGateways = Gateway::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count();
        $registered = (int) data_get($cached, 'registrations.count', 0);
        $status = $activeGateways > 0 && $registered === 0 ? 'error' : 'ok';

        return [
            'label' => 'Gateways',
            'status' => $status,
            'detail' => sprintf('%d active, %d registered', $activeGateways, $registered),
            'meta' => [
                'active_gateways' => $activeGateways,
                'registered' => $registered,
                'checked_at' => data_get($cached, 'checked_at'),
            ],
        ];
    }

    private function queueHealthCheck(): array
    {
        try {
            $pendingJobs = DB::table('jobs')->count();

            return [
                'label' => 'Queue',
                'status' => $pendingJobs > 100 ? 'error' : 'ok',
                'detail' => sprintf('%d pending job(s)', $pendingJobs),
                'meta' => [
                    'pending_jobs' => $pendingJobs,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Queue',
                'status' => 'error',
                'detail' => 'Queue table unavailable',
                'meta' => [
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    private function webhookHealthCheck(Tenant $tenant): array
    {
        $failedAttempts = WebhookDeliveryAttempt::query()
            ->whereHas('webhook', fn ($query) => $query->where('tenant_id', $tenant->id))
            ->where('success', false)
            ->count();

        return [
            'label' => 'Webhooks',
            'status' => $failedAttempts > 0 ? 'error' : 'ok',
            'detail' => sprintf('%d failed delivery attempt(s)', $failedAttempts),
            'meta' => [
                'failed_attempts' => $failedAttempts,
            ],
        ];
    }

    private function parseFreeswitchStatus(?string $response): array
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

    private function uiContext(Request $request, Tenant $tenant): array
    {
        $user = $request->user();
        $enabledAliases = collect(Module::allEnabled())->map(fn ($module) => (string) $module->get('alias'))->all();

        $baseNav = [
            ['label' => 'Dashboard', 'route' => 'ui.dashboard', 'parameters' => ['tenant' => $tenant], 'module' => null],
            ['label' => 'Extensions', 'route' => 'ui.extensions', 'parameters' => ['tenant' => $tenant], 'module' => 'pbx-routing'],
            ['label' => 'System Health', 'route' => 'ui.health', 'parameters' => ['tenant' => $tenant], 'module' => null],
            ['label' => 'Modules', 'route' => 'ui.modules', 'parameters' => [], 'module' => null],
        ];

        return [
            'tenant' => $tenant,
            'tenants' => $user?->isAdmin()
                ? Tenant::query()->orderBy('name')->get(['id', 'name'])
                : Tenant::query()->whereKey($tenant->id)->get(['id', 'name']),
            'user' => $user,
            'navigation' => collect($baseNav)
                ->filter(fn ($item) => ! $item['module'] || in_array($item['module'], $enabledAliases, true))
                ->values()
                ->all(),
            'expansion_navigation' => collect([
                [
                    'label' => 'Routing',
                    'items' => [
                        ['label' => 'DIDs', 'route' => 'ui.routing.dids'],
                        ['label' => 'Ring Groups', 'route' => 'ui.routing.ring-groups'],
                        ['label' => 'IVR', 'route' => 'ui.routing.ivr'],
                        ['label' => 'Time Conditions', 'route' => 'ui.routing.time-conditions'],
                    ],
                ],
                [
                    'label' => 'Contact Center',
                    'items' => [
                        ['label' => 'Queues', 'route' => 'ui.contact-center.queues'],
                        ['label' => 'Agents', 'route' => 'ui.contact-center.agents'],
                        ['label' => 'Wallboard', 'route' => 'ui.contact-center.wallboard'],
                    ],
                ],
                [
                    'label' => 'Automation',
                    'items' => [
                        ['label' => 'Webhooks', 'route' => 'ui.automation.webhooks'],
                        ['label' => 'Event Log Viewer', 'route' => 'ui.automation.event-log-viewer'],
                        ['label' => 'Retry Management', 'route' => 'ui.automation.retry-management'],
                    ],
                ],
                [
                    'label' => 'Analytics',
                    'items' => [
                        ['label' => 'Recordings', 'route' => 'ui.analytics.recordings'],
                        ['label' => 'SLA Trends', 'route' => 'ui.analytics.sla-trends'],
                        ['label' => 'Call Volume', 'route' => 'ui.analytics.call-volume'],
                    ],
                ],
                [
                    'label' => 'Media Policy',
                    'items' => [
                        ['label' => 'Gateways', 'route' => 'ui.media-policy.gateways'],
                        ['label' => 'Codec Policy', 'route' => 'ui.media-policy.codec-policy'],
                        ['label' => 'Transcoding Stats', 'route' => 'ui.media-policy.transcoding-stats'],
                    ],
                ],
                [
                    'label' => 'Admin',
                    'items' => [
                        ['label' => 'Tenants', 'route' => 'ui.admin.tenants'],
                        ['label' => 'Node Health per FS', 'route' => 'ui.admin.node-health-per-fs'],
                        ['label' => 'Fraud Alerts', 'route' => 'ui.admin.fraud-alerts'],
                    ],
                    'admin_only' => true,
                ],
            ])->filter(fn ($section) => ! ($section['admin_only'] ?? false) || $user?->isAdmin())
                ->values()
                ->all(),
            'ws_stream' => config('services.nizam.ws_url'),
            'ws_jwt' => $user instanceof User && config('services.nizam.ws_jwt_secret')
                ? $this->websocketJwt($user, $tenant)
                : null,
        ];
    }

    private function websocketJwt(User $user, Tenant $tenant): string
    {
        $ttlMinutes = max(1, (int) config('services.nizam.ws_jwt_ttl_minutes', 5));
        $secret = (string) config('services.nizam.ws_jwt_secret');
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']) ?: '{}');
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => $user->id,
            'tenant_id' => $tenant->id,
            'exp' => now()->addMinutes($ttlMinutes)->timestamp,
        ]) ?: '{}');
        $signature = hash_hmac('sha256', "{$header}.{$payload}", $secret, true);

        return "{$header}.{$payload}.".$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function surfacePages(): array
    {
        return [
            'routing.dids' => ['title' => 'DIDs', 'section' => 'Routing'],
            'routing.ring-groups' => ['title' => 'Ring Groups', 'section' => 'Routing'],
            'routing.ivr' => ['title' => 'IVR', 'section' => 'Routing'],
            'routing.time-conditions' => ['title' => 'Time Conditions', 'section' => 'Routing'],
            'contact-center.queues' => ['title' => 'Queues', 'section' => 'Contact Center'],
            'contact-center.agents' => ['title' => 'Agents', 'section' => 'Contact Center'],
            'contact-center.wallboard' => ['title' => 'Wallboard', 'section' => 'Contact Center'],
            'automation.webhooks' => ['title' => 'Webhooks', 'section' => 'Automation'],
            'automation.event-log-viewer' => ['title' => 'Event Log Viewer', 'section' => 'Automation'],
            'automation.retry-management' => ['title' => 'Retry Management', 'section' => 'Automation'],
            'analytics.recordings' => ['title' => 'Recordings', 'section' => 'Analytics'],
            'analytics.sla-trends' => ['title' => 'SLA Trends', 'section' => 'Analytics'],
            'analytics.call-volume' => ['title' => 'Call Volume', 'section' => 'Analytics'],
            'media-policy.gateways' => ['title' => 'Gateways', 'section' => 'Media Policy'],
            'media-policy.codec-policy' => ['title' => 'Codec Policy', 'section' => 'Media Policy'],
            'media-policy.transcoding-stats' => ['title' => 'Transcoding Stats', 'section' => 'Media Policy'],
            'admin.tenants' => ['title' => 'Tenants', 'section' => 'Admin', 'admin_only' => true],
            'admin.node-health-per-fs' => ['title' => 'Node Health per FS', 'section' => 'Admin', 'admin_only' => true],
            'admin.fraud-alerts' => ['title' => 'Fraud Alerts', 'section' => 'Admin', 'admin_only' => true],
        ];
    }

    private function surfacePageData(string $page, Tenant $tenant): array
    {
        return match ($page) {
            'routing.dids' => [
                'description' => 'Inbound DID inventory and destinations.',
                'columns' => ['Number', 'Destination', 'Active'],
                'rows' => Did::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (Did $did) => [$did->number, (string) $did->destination_type, $did->is_active ? 'Yes' : 'No'])
                    ->all(),
            ],
            'routing.ring-groups' => [
                'description' => 'Ring groups and failover behavior.',
                'columns' => ['Name', 'Strategy', 'Active'],
                'rows' => RingGroup::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (RingGroup $group) => [$group->name, (string) $group->strategy, $group->is_active ? 'Yes' : 'No'])
                    ->all(),
            ],
            'routing.ivr' => [
                'description' => 'IVR menus and timeout destinations.',
                'columns' => ['Name', 'Timeout', 'Active'],
                'rows' => Ivr::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (Ivr $ivr) => [$ivr->name, (string) $ivr->timeout, $ivr->is_active ? 'Yes' : 'No'])
                    ->all(),
            ],
            'routing.time-conditions' => [
                'description' => 'Time-based routing conditions.',
                'columns' => ['Name', 'Match destination', 'Active'],
                'rows' => TimeCondition::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (TimeCondition $condition) => [$condition->name, (string) $condition->match_destination_type, $condition->is_active ? 'Yes' : 'No'])
                    ->all(),
            ],
            'contact-center.queues' => [
                'description' => 'Queue definitions and waiting strategy.',
                'columns' => ['Name', 'Strategy', 'Max wait'],
                'rows' => Queue::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (Queue $queue) => [$queue->name, (string) $queue->strategy, (string) $queue->max_wait_time])
                    ->all(),
            ],
            'contact-center.agents' => [
                'description' => 'Agent roster and live state.',
                'columns' => ['Name', 'Role', 'State'],
                'rows' => Agent::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (Agent $agent) => [$agent->name, (string) $agent->role, (string) $agent->state])
                    ->all(),
            ],
            'contact-center.wallboard' => [
                'description' => 'Realtime operational queue snapshot.',
                'stats' => [
                    ['label' => 'Waiting calls', 'value' => QueueEntry::query()->where('tenant_id', $tenant->id)->where('status', QueueEntry::STATUS_WAITING)->count()],
                    ['label' => 'Available agents', 'value' => Agent::query()->where('tenant_id', $tenant->id)->where('state', Agent::STATE_AVAILABLE)->where('is_active', true)->count()],
                ],
            ],
            'automation.webhooks' => [
                'description' => 'Configured outbound webhooks.',
                'columns' => ['Description', 'URL', 'Active'],
                'rows' => Webhook::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (Webhook $webhook) => [(string) $webhook->description, $webhook->url, $webhook->is_active ? 'Yes' : 'No'])
                    ->all(),
            ],
            'automation.event-log-viewer' => [
                'description' => 'Latest platform call/event activity.',
                'columns' => ['Event type', 'Call UUID', 'Occurred'],
                'rows' => CallEventLog::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest('occurred_at')
                    ->limit(25)
                    ->get()
                    ->map(fn (CallEventLog $event) => [$event->event_type, (string) $event->call_uuid, $event->occurred_at?->toDateTimeString() ?? ''])
                    ->all(),
            ],
            'automation.retry-management' => [
                'description' => 'Failed webhook delivery attempts requiring retry.',
                'columns' => ['Event', 'HTTP status', 'Delivered at'],
                'rows' => WebhookDeliveryAttempt::query()
                    ->whereHas('webhook', fn ($query) => $query->where('tenant_id', $tenant->id))
                    ->where('success', false)
                    ->latest('delivered_at')
                    ->limit(25)
                    ->get()
                    ->map(fn (WebhookDeliveryAttempt $attempt) => [$attempt->event_type, (string) $attempt->response_status, $attempt->delivered_at?->toDateTimeString() ?? ''])
                    ->all(),
            ],
            'analytics.recordings' => [
                'description' => 'Call recording inventory.',
                'columns' => ['File', 'Duration', 'Direction'],
                'rows' => Recording::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (Recording $recording) => [$recording->file_name, (string) $recording->duration, (string) $recording->direction])
                    ->all(),
            ],
            'analytics.sla-trends' => [
                'description' => 'Recent queue SLA service levels.',
                'columns' => ['Queue', 'Period start', 'Service level'],
                'rows' => QueueMetric::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest('period_start')
                    ->limit(25)
                    ->get()
                    ->map(fn (QueueMetric $metric) => [(string) $metric->queue_id, $metric->period_start?->toDateTimeString() ?? '', (string) $metric->service_level])
                    ->all(),
            ],
            'analytics.call-volume' => [
                'description' => 'Call traffic counters.',
                'stats' => [
                    ['label' => 'Total calls', 'value' => CallDetailRecord::query()->where('tenant_id', $tenant->id)->count()],
                    ['label' => 'Active calls', 'value' => CallDetailRecord::query()->where('tenant_id', $tenant->id)->whereNull('end_stamp')->count()],
                ],
            ],
            'media-policy.gateways' => [
                'description' => 'SIP gateway inventory and status.',
                'columns' => ['Name', 'Host', 'Active'],
                'rows' => Gateway::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (Gateway $gateway) => [$gateway->name, $gateway->host, $gateway->is_active ? 'Yes' : 'No'])
                    ->all(),
            ],
            'media-policy.codec-policy' => [
                'description' => 'Tenant codec policy configuration.',
                'columns' => ['Key', 'Value'],
                'rows' => collect($tenant->codec_policy ?? [])
                    ->map(fn ($value, $key) => [(string) $key, is_scalar($value) ? (string) $value : (json_encode($value) ?: '[unencodable]')])
                    ->values()
                    ->all(),
            ],
            'media-policy.transcoding-stats' => [
                'description' => 'Codec negotiation/transcoding visibility.',
                'columns' => ['Read codec', 'Write codec', 'Negotiated codec'],
                'rows' => CallDetailRecord::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereNotNull('read_codec')
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (CallDetailRecord $cdr) => [(string) $cdr->read_codec, (string) $cdr->write_codec, (string) $cdr->negotiated_codec])
                    ->all(),
            ],
            'admin.tenants' => [
                'description' => 'Tenant lifecycle and status.',
                'columns' => ['Name', 'Domain', 'Status'],
                'rows' => Tenant::query()
                    ->orderBy('name')
                    ->limit(50)
                    ->get()
                    ->map(fn (Tenant $listedTenant) => [$listedTenant->name, $listedTenant->domain, $listedTenant->status])
                    ->all(),
            ],
            'admin.node-health-per-fs' => [
                'description' => 'Gateway-node proxy health view.',
                'stats' => [
                    ['label' => 'Active gateways', 'value' => Gateway::query()->where('is_active', true)->count()],
                    ['label' => 'Total gateways', 'value' => Gateway::query()->count()],
                ],
            ],
            'admin.fraud-alerts' => [
                'description' => 'Open fraud/security alert overview.',
                'columns' => ['Severity', 'Metric', 'Status'],
                'rows' => Alert::query()
                    ->where('status', Alert::STATUS_OPEN)
                    ->latest()
                    ->limit(25)
                    ->get()
                    ->map(fn (Alert $alert) => [$alert->severity, $alert->metric, $alert->status])
                    ->all(),
            ],
            default => [],
        };
    }
}
