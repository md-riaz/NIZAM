<x-layouts.app :ui="$ui">
    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Create extension" class="lg:col-span-1">
            <form hx-post="{{ route('ui.extensions.store', ['tenant' => $ui['tenant']]) }}" hx-target="#extensions-table" hx-swap="outerHTML" class="space-y-3">
                @csrf
                <x-ui.input name="extension" label="Extension" required />
                <x-ui.input name="password" label="Password" required type="password" />
                <x-ui.input name="directory_first_name" label="First name" required />
                <x-ui.input name="directory_last_name" label="Last name" required />
                <x-ui.button type="submit">Create</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Extensions" class="lg:col-span-2">
            @include('ui.extensions._table', ['tenant' => $ui['tenant'], 'extensions' => $extensions])
        </x-ui.card>
    </div>
</x-layouts.app>
