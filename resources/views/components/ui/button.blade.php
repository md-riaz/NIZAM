@props([
    'variant' => 'default',
    'type' => 'button',
])

@php
$variants = [
    'default' => 'bg-primary text-background hover:opacity-90',
    'outline' => 'border border-border bg-background text-foreground hover:bg-muted',
    'destructive' => 'bg-destructive text-background hover:opacity-90',
    'ghost' => 'bg-transparent text-foreground hover:bg-muted',
];
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring '.($variants[$variant] ?? $variants['default'])]) }}>
    {{ $slot }}
</button>
