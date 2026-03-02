@props(['label' => null, 'name'])

<label class="space-y-1 text-sm">
    @if($label)
        <span class="font-medium">{{ $label }}</span>
    @endif
    <input name="{{ $name }}" {{ $attributes->merge(['class' => 'w-full rounded-md border border-border bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring']) }} />
</label>
