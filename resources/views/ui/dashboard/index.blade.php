<x-layouts.app :ui="$ui">
    <div class="space-y-6" data-dashboard-stream="{{ $ui['ws_stream'] }}" data-jwt="{{ $ui['ws_jwt'] }}">
        <div>
            <h2 class="text-xl font-semibold">Tenant Dashboard</h2>
            <p class="mt-1 text-sm text-muted-foreground">Live tenant metrics plus core platform dependency health.</p>
        </div>

        <div>
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-lg font-semibold">Platform status</h3>
                <x-ui.badge :variant="$systemHealth['summary']['status'] === 'healthy' ? 'success' : 'destructive'">
                    {{ strtoupper($systemHealth['summary']['status']) }}
                </x-ui.badge>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($systemHealth['checks'] as $check)
                    <x-ui.card :title="$check['label']">
                        <div class="space-y-2">
                            <x-ui.badge :variant="$check['status'] === 'ok' ? 'success' : 'destructive'">
                                {{ strtoupper($check['status']) }}
                            </x-ui.badge>
                            <p class="text-sm text-muted-foreground">{{ $check['detail'] }}</p>
                            @if(!empty($check['meta']))
                                <div class="space-y-1 text-xs text-muted-foreground">
                                    @foreach($check['meta'] as $metaLabel => $metaValue)
                                        @if($metaValue !== null && $metaValue !== '')
                                            <div><span class="font-medium">{{ str_replace('_', ' ', ucfirst($metaLabel)) }}:</span> {{ is_scalar($metaValue) ? $metaValue : json_encode($metaValue) }}</div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="mb-3 text-lg font-semibold">Tenant metrics</h3>
            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.card title="Active calls"><p data-metric="active_calls" class="text-2xl font-bold">{{ $metrics['active_calls'] }}</p></x-ui.card>
                <x-ui.card title="Waiting calls"><p data-metric="waiting_calls" class="text-2xl font-bold">{{ $metrics['waiting_calls'] }}</p></x-ui.card>
                <x-ui.card title="Available agents"><p data-metric="available_agents" class="text-2xl font-bold">{{ $metrics['available_agents'] }}</p></x-ui.card>
                <x-ui.card title="SLA %"><p data-metric="sla_percent" class="text-2xl font-bold">{{ number_format($metrics['sla_percent'], 2) }}</p></x-ui.card>
                <x-ui.card title="Gateway status"><p data-metric="gateway_status" class="text-2xl font-bold">{{ $metrics['gateway_status'] }}</p></x-ui.card>
                <x-ui.card title="Webhook health"><p data-metric="webhook_health" class="text-2xl font-bold">{{ $metrics['webhook_health'] }}</p></x-ui.card>
            </div>
        </div>
    </div>
</x-layouts.app>
