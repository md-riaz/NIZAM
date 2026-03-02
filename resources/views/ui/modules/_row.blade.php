<tr id="module-{{ $module['name'] }}">
    <td class="px-3 py-2">{{ $module['name'] }}</td>
    <td class="px-3 py-2">{{ $module['alias'] }}</td>
    <td class="px-3 py-2">
        <x-ui.badge :variant="$module['enabled'] ? 'success' : 'destructive'">{{ $module['enabled'] ? 'enabled' : 'disabled' }}</x-ui.badge>
    </td>
    <td class="px-3 py-2">
        <x-ui.badge :variant="$module['registry_enabled'] ? 'success' : 'destructive'">{{ $module['registry_enabled'] ? 'enabled' : 'disabled' }}</x-ui.badge>
    </td>
    <td class="px-3 py-2">
        <form method="POST" action="{{ route('ui.modules.toggle', ['moduleName' => $module['name']]) }}" hx-post="{{ route('ui.modules.toggle', ['moduleName' => $module['name']]) }}" hx-target="#module-{{ $module['name'] }}" hx-swap="outerHTML">
            @csrf
            <x-ui.button type="submit" variant="outline">Toggle</x-ui.button>
        </form>
    </td>
</tr>
