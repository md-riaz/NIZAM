<x-layouts.app :ui="$ui">
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">{{ $page['section'] }} · {{ $page['title'] }}</h2>
        <x-ui.card :title="$page['title']">
            <p class="text-sm text-muted-foreground">
                {{ $data['description'] ?? "Browser page scaffold for {$page['title']}. This surface is now routable in UI." }}
            </p>
        </x-ui.card>

        @if(! empty($data['stats']))
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($data['stats'] as $stat)
                    <x-ui.card :title="$stat['label']">
                        <p class="text-2xl font-bold">{{ $stat['value'] }}</p>
                    </x-ui.card>
                @endforeach
            </div>
        @endif

        @if(! empty($data['columns']))
            <x-ui.card title="Data">
                <x-ui.table>
                    <thead class="bg-muted">
                        <tr>
                            @foreach($data['columns'] as $column)
                                <th class="px-3 py-2 text-left">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($data['rows'] ?? [] as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td class="px-3 py-2">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-2 text-muted-foreground" colspan="{{ count($data['columns']) }}">No records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
