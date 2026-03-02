@props(['variant' => 'default'])

@php
$variants = [
    'default' => 'bg-muted text-foreground',
    'success' => 'bg-primary text-background',
    'destructive' => 'bg-destructive text-background',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-1 text-xs font-medium '.($variants[$variant] ?? $variants['default'])]) }}>{{ $slot }}</span>
