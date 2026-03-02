<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'NIZAM') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-muted">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-4">
        <x-ui.card class="w-full">
            {{ $slot }}
        </x-ui.card>
    </main>
</body>
</html>
