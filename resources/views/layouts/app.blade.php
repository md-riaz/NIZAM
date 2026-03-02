<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'NIZAM') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/htmx.org@1.9.12" defer></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="h-full bg-muted text-foreground" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        <aside class="w-64 border-r border-border bg-background p-4" :class="sidebarOpen ? 'block' : 'hidden md:block'">
            <h1 class="mb-4 text-lg font-semibold">NIZAM UI v1</h1>
            <nav class="space-y-2">
                @foreach($ui['navigation'] as $item)
                    <a href="{{ route($item['route'], $item['parameters']) }}" class="block rounded-md px-3 py-2 text-sm hover:bg-muted">{{ $item['label'] }}</a>
                @endforeach
                <div class="mt-4 border-t border-border pt-3">
                    @foreach($ui['expansion_navigation'] as $section)
                        <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ $section['label'] }}</p>
                        @foreach($section['items'] as $item)
                            <a href="{{ route($item['route']) }}" class="block rounded-md px-3 py-1 text-sm text-muted-foreground hover:bg-muted hover:text-foreground">{{ $item['label'] }}</a>
                        @endforeach
                    @endforeach
                </div>
            </nav>
        </aside>

        <div class="flex-1">
            <header class="border-b border-border bg-background p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <x-ui.button variant="outline" @click="sidebarOpen = !sidebarOpen">Menu</x-ui.button>
                        <form method="GET" action="{{ route('ui.dashboard') }}">
                            <select name="tenant" class="rounded-md border border-border bg-background px-3 py-2 text-sm" onchange="this.form.submit()">
                                @foreach($ui['tenants'] as $tenantOption)
                                    <option value="{{ $tenantOption->id }}" @selected($tenantOption->id === $ui['tenant']->id)>{{ $tenantOption->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.button variant="outline" onclick="window.toggleTheme()">Dark mode</x-ui.button>
                        <x-ui.badge>{{ $ui['user']->email }}</x-ui.badge>
                    </div>
                </div>
            </header>

            <main class="p-4">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
