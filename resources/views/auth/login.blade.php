<x-layouts.auth>
    @php
        $prefillDemoCredentials = ! app()->environment('production');
        $demoEmail = 'admin@nizam.local';
        $demoPassword = 'password';
    @endphp

    <h1 class="mb-4 text-xl font-semibold">Sign in</h1>

    @if($errors->any())
        <div class="mb-3 rounded-md border border-destructive/30 bg-destructive/10 p-2 text-sm text-destructive">
            {{ $errors->first('credentials') ?: $errors->first() }}
        </div>
    @endif

    @if($prefillDemoCredentials)
        <div class="mb-3 rounded-md border border-border bg-muted/40 p-2 text-xs text-muted-foreground">
            Demo login is prefilled outside production.
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-3">
        @csrf
        <x-ui.input name="email" label="Email" type="email" required :value="old('email', $prefillDemoCredentials ? $demoEmail : '')" />
        <x-ui.input name="password" label="Password" type="password" required :value="$prefillDemoCredentials ? $demoPassword : ''" />

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="rounded border-border" />
            <span>Remember me</span>
        </label>

        <x-ui.button type="submit" class="w-full">Sign in</x-ui.button>
    </form>
</x-layouts.auth>
