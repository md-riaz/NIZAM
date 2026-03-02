<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\CallDetailRecord;
use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\QueueMetric;
use App\Models\Tenant;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Nwidart\Modules\Facades\Module;

class UiController extends Controller
{
    public function dashboard(Request $request, ?Tenant $tenant = null): View
    {
        $tenant = $this->resolveTenant($request, $tenant);

        return view('ui.dashboard.index', [
            'ui' => $this->uiContext($request, $tenant),
            'metrics' => $this->dashboardMetrics($tenant),
        ]);
    }

    public function systemHealth(Request $request, ?Tenant $tenant = null): View
    {
        $tenant = $this->resolveTenant($request, $tenant);

        return view('ui.dashboard.health', [
            'ui' => $this->uiContext($request, $tenant),
            'health' => [
                'switch_node_health' => Gateway::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
                'event_lag' => (int) Queue::query()->where('tenant_id', $tenant->id)->avg('max_wait_time'),
                'webhook_backlog' => WebhookDeliveryAttempt::query()->whereHas('webhook', fn ($query) => $query->where('tenant_id', $tenant->id))->where('success', false)->count(),
                'active_channels' => CallDetailRecord::query()->where('tenant_id', $tenant->id)->whereNull('end_stamp')->count(),
                'fraud_alerts' => 0,
            ],
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
                    'registry_enabled' => app(\App\Modules\ModuleRegistry::class)->isEnabled((string) $module->get('alias')),
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
                'registry_enabled' => app(\App\Modules\ModuleRegistry::class)->isEnabled((string) $module->get('alias')),
            ],
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

            return Tenant::query()->findOrFail($user->tenant_id);
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
            'sla_percent' => number_format((float) QueueMetric::query()->where('tenant_id', $tenant->id)->avg('service_level'), 2),
            'gateway_status' => Gateway::query()->where('tenant_id', $tenant->id)->where('is_active', true)->exists() ? 'up' : 'down',
            'webhook_health' => WebhookDeliveryAttempt::query()->whereHas('webhook', fn ($query) => $query->where('tenant_id', $tenant->id))->where('success', false)->exists() ? 'degraded' : 'healthy',
        ];
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
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'user' => $user,
            'navigation' => collect($baseNav)
                ->filter(fn ($item) => ! $item['module'] || in_array($item['module'], $enabledAliases, true))
                ->values()
                ->all(),
            'platform_navigation' => $user?->isAdmin() ? [
                ['label' => 'Admin Dashboard', 'href' => '/api/v1/admin/dashboard'],
            ] : [],
            'ws_stream' => config('services.nizam.ws_url'),
            'ws_jwt' => null,
        ];
    }
}
