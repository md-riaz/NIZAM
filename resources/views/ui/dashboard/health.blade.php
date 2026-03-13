<x-layouts.app :ui="$ui">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">System Health</h2>
                <p class="mt-1 text-sm text-muted-foreground">Status of the core services NIZAM depends on.</p>
            </div>
            <div class="text-right">
                <x-ui.badge :variant="$healthSummary['status'] === 'healthy' ? 'success' : 'destructive'">
                    {{ strtoupper($healthSummary['status']) }}
                </x-ui.badge>
                <p class="mt-2 text-xs text-muted-foreground">Checked {{ $healthSummary['checked_at']->toDateTimeString() }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($health as $check)
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
</x-layouts.app>
