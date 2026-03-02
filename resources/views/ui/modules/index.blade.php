<x-layouts.app :ui="$ui">
    <h2 class="mb-4 text-xl font-semibold">Module Activation</h2>
    <x-ui.table>
        <thead class="bg-muted">
        <tr>
            <th class="px-3 py-2 text-left">Module</th>
            <th class="px-3 py-2 text-left">Alias</th>
            <th class="px-3 py-2 text-left">nwidart</th>
            <th class="px-3 py-2 text-left">Registry</th>
            <th class="px-3 py-2 text-left">Action</th>
        </tr>
        </thead>
        <tbody id="module-table" class="divide-y divide-border">
        @foreach($modules as $module)
            @include('ui.modules._row', ['module' => $module])
        @endforeach
        </tbody>
    </x-ui.table>
</x-layouts.app>
