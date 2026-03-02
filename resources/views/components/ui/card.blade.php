@props(['title' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-background p-4 shadow-sm']) }}>
    @if($title)
        <h3 class="mb-3 text-sm font-semibold">{{ $title }}</h3>
    @endif
    {{ $slot }}
</div>
