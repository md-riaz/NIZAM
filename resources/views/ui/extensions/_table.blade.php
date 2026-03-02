<x-ui.table id="extensions-table">
    <thead class="bg-muted">
    <tr>
        <th class="px-3 py-2 text-left">Extension</th>
        <th class="px-3 py-2 text-left">Name</th>
        <th class="px-3 py-2 text-left">Status</th>
        <th class="px-3 py-2 text-left">Actions</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-border">
    @forelse($extensions as $extension)
        <tr>
            <td class="px-3 py-2">{{ $extension->extension }}</td>
            <td class="px-3 py-2">{{ $extension->directory_first_name }} {{ $extension->directory_last_name }}</td>
            <td class="px-3 py-2">
                <x-ui.badge :variant="$extension->is_active ? 'success' : 'destructive'">{{ $extension->is_active ? 'active' : 'inactive' }}</x-ui.badge>
            </td>
            <td class="px-3 py-2">
                <form hx-delete="{{ route('ui.extensions.destroy', ['tenant' => $tenant, 'extension' => $extension]) }}" hx-target="#extensions-table" hx-swap="outerHTML">
                    @csrf
                    <x-ui.button type="submit" variant="destructive">Delete</x-ui.button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="4" class="px-3 py-6 text-center text-sm">No extensions</td></tr>
    @endforelse
    </tbody>
</x-ui.table>
