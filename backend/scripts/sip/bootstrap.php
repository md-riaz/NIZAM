<?php

use App\Models\Extension;
use App\Models\SipProfileSetting;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = \Illuminate\Container\Container::getInstance();

if (! $app instanceof \Illuminate\Contracts\Foundation\Application) {
    $app = require __DIR__.'/../../bootstrap/app.php';
}

if (! $app->hasBeenBootstrapped()) {
    $app->make(Kernel::class)->bootstrap();
}

\Illuminate\Container\Container::setInstance($app);

if (function_exists('app')) {
    app()->instance('app', $app);
}

$app->setBasePath(dirname(__DIR__, 2));

if (! function_exists('sip_test_resolve_extension')) {
    function sip_test_resolve_extension(string $extensionNumber, ?string $domain = null): array
    {
        $extensionQuery = Extension::with('tenant')
            ->where('extension', $extensionNumber)
            ->where('is_active', true);

        if ($domain !== null) {
            $extensionQuery->whereHas('tenant', fn ($query) => $query->where('domain', $domain));
        }

        $extension = $extensionQuery->firstOrFail();

        $internalPort = SipProfileSetting::query()
            ->whereHas('profile', fn ($query) => $query->where('name', 'internal'))
            ->where('name', 'sip-port')
            ->where('is_enabled', true)
            ->value('value') ?? '5060';

        return [
            'extension' => $extension->extension,
            'password' => $extension->password,
            'domain' => $extension->tenant->domain,
            'tenant_slug' => $extension->tenant->slug,
            'internal_port' => (string) $internalPort,
            'host_port' => (string) env('FREESWITCH_SIP_PORT', '25060'),
            'host_target' => '127.0.0.1',
            'docker_target' => env('FREESWITCH_HOST', 'freeswitch'),
        ];
    }
}
