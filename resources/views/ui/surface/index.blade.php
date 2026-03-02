<x-layouts.app :ui="$ui">
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">{{ $page['section'] }} · {{ $page['title'] }}</h2>
        <x-ui.card :title="$page['title']">
            <p class="text-sm text-muted-foreground">
                Browser page scaffold for {{ $page['title'] }}. This surface is now routable in UI.
            </p>
        </x-ui.card>
    </div>
</x-layouts.app>
