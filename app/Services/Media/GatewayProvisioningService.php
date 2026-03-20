<?php

namespace App\Services\Media;

use App\Models\Gateway;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GatewayProvisioningService
{
    public function __construct(
        protected FreeSwitchCommandService $freeSwitch,
    ) {}

    public function sync(Gateway $gateway): void
    {
        $directory = $this->directory();
        File::ensureDirectoryExists($directory);

        if (! $gateway->is_active) {
            $this->remove($gateway);
            return;
        }

        File::put($this->filePath($gateway), $this->render($gateway));
        $this->reloadProfile($gateway);
    }

    public function remove(Gateway $gateway): void
    {
        $filePath = $this->filePath($gateway);
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        $this->reloadProfile($gateway);
    }

    public function syncAll(iterable $gateways): void
    {
        File::ensureDirectoryExists($this->directory());

        foreach ($gateways as $gateway) {
            if ($gateway->is_active) {
                File::put($this->filePath($gateway), $this->render($gateway));
            } else {
                $filePath = $this->filePath($gateway);
                if (File::exists($filePath)) {
                    File::delete($filePath);
                }
            }
        }

        $this->reloadProfile();
    }

    public function render(Gateway $gateway): string
    {
        $profile = $this->profile();
        $name = $this->gatewayIdentifier($gateway);
        $proxyHost = $gateway->host;
        $port = $gateway->port ?: 5060;
        $realm = $gateway->realm ?: $gateway->host;
        $proxy = str_contains($proxyHost, ':') ? $proxyHost : $proxyHost.':'.$port;
        $register = $gateway->username && $gateway->password ? 'true' : 'false';
        $fromUser = $gateway->username ?: $gateway->name;
        $contactParams = match ($gateway->transport) {
            'tcp' => 'transport=tcp',
            'tls' => 'transport=tls',
            default => 'transport=udp',
        };

        $params = [
            'username' => $gateway->username,
            'password' => $gateway->password,
            'realm' => $realm,
            'proxy' => $proxy,
            'register-proxy' => $proxy,
            'expire-seconds' => '3600',
            'retry-seconds' => '30',
            'caller-id-in-from' => 'true',
            'extension' => $fromUser,
            'from-user' => $fromUser,
            'from-domain' => $realm,
            'register' => $register,
            'contact-params' => $contactParams,
            'ping' => '25',
            'profile' => $profile,
        ];

        $xml = ["<include>", "  <gateway name=\"{$this->xml($name)}\">"];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $xml[] = "    <param name=\"{$this->xml($key)}\" value=\"{$this->xml((string) $value)}\"/>";
        }
        $xml[] = '  </gateway>';
        $xml[] = '</include>';

        return implode("\n", $xml)."\n";
    }

    protected function reloadProfile(?Gateway $gateway = null): void
    {
        $profile = $this->profile();
        $reload = $this->freeSwitch->execute('reloadxml');
        $rescan = $this->freeSwitch->execute('sofia', ['profile', $profile, 'rescan']);

        if (($gateway?->is_active ?? false) && $gateway) {
            $this->freeSwitch->execute('sofia', ['profile', $profile, 'killgw', $this->gatewayIdentifier($gateway)]);
            $this->freeSwitch->execute('sofia', ['profile', $profile, 'startgw', $this->gatewayIdentifier($gateway)]);
        }

        Log::info('Gateway profile reloaded', [
            'gateway_id' => $gateway?->id,
            'profile' => $profile,
            'reload' => $reload,
            'rescan' => $rescan,
        ]);
    }

    protected function filePath(Gateway $gateway): string
    {
        return rtrim($this->directory(), '/').'/v_'.$gateway->id.'.xml';
    }

    protected function directory(): string
    {
        return config('nizam.gateway_provisioning.external_directory', storage_path('freeswitch/sip_profiles/external'));
    }

    protected function profile(): string
    {
        return config('nizam.gateway_provisioning.profile', 'external');
    }

    protected function gatewayIdentifier(Gateway $gateway): string
    {
        return 'v_'.$gateway->id;
    }

    protected function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }
}
