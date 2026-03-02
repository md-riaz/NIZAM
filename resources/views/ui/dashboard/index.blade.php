<x-layouts.app :ui="$ui">
    <div class="space-y-4" data-dashboard-stream="{{ $ui['ws_stream'] }}" data-jwt="{{ $ui['ws_jwt'] }}">
        <h2 class="text-xl font-semibold">Tenant Dashboard</h2>

        <div class="grid gap-4 md:grid-cols-3">
            <x-ui.card title="Active calls"><p data-metric="active_calls" class="text-2xl font-bold">{{ $metrics['active_calls'] }}</p></x-ui.card>
            <x-ui.card title="Waiting calls"><p data-metric="waiting_calls" class="text-2xl font-bold">{{ $metrics['waiting_calls'] }}</p></x-ui.card>
            <x-ui.card title="Available agents"><p data-metric="available_agents" class="text-2xl font-bold">{{ $metrics['available_agents'] }}</p></x-ui.card>
            <x-ui.card title="SLA %"><p data-metric="sla_percent" class="text-2xl font-bold">{{ $metrics['sla_percent'] }}</p></x-ui.card>
            <x-ui.card title="Gateway status"><p data-metric="gateway_status" class="text-2xl font-bold">{{ $metrics['gateway_status'] }}</p></x-ui.card>
            <x-ui.card title="Webhook health"><p data-metric="webhook_health" class="text-2xl font-bold">{{ $metrics['webhook_health'] }}</p></x-ui.card>
        </div>
    </div>
</x-layouts.app>
