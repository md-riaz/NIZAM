<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'NIZAM') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-muted p-4">
    <x-ui.card class="max-w-xl text-center">
        <h1 class="mb-2 text-2xl font-bold">NIZAM UI v1</h1>
        <p class="mb-4 text-sm">Blade + Tailwind tokens + Alpine + HTMX control surface.</p>
        <a href="{{ route('ui.dashboard') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-background">Open Dashboard</a>
    </x-ui.card>
</body>
</html>
