@props(['title' => ''])

<div x-data="{ open: false }" {{ $attributes->merge(['class' => 'relative']) }}>
    <div @click="open = true">{{ $trigger ?? '' }}</div>
    <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-foreground/40" @click="open = false"></div>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4">
        <div class="w-full max-w-lg rounded-lg border border-border bg-background p-4">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <x-ui.button variant="ghost" @click="open = false">Close</x-ui.button>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
