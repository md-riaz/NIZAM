<x-layouts.app :ui="$ui">
    <h2 class="mb-4 text-xl font-semibold">System Health</h2>
    <div class="grid gap-4 md:grid-cols-3">
        @foreach($health as $label => $value)
            <x-ui.card :title="str_replace('_', ' ', ucfirst($label))">
                <p class="text-2xl font-bold">{{ $value }}</p>
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.app>
