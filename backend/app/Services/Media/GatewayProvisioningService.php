<?php

namespace App\Services\Media;

use App\Models\Gateway;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GatewayProvisioningService
{
    public function __construct(
        protected FreeSwitchCommandService $freeSwitch,
        protected ?GatewayLifecyclePlanner $planner = null,
        protected ?FreeSwitchGatewayLifecycleExecutor $executor = null,
    ) {
        $this->planner ??= app(GatewayLifecyclePlanner::class);
        $this->executor ??= app(FreeSwitchGatewayLifecycleExecutor::class);
    }

    public function syncCreated(Gateway $gateway): array
    {
        return $this->applyPlan($gateway, $this->planner->forCreate($gateway));
    }

    public function syncUpdated(Gateway $gateway, array $original): array
    {
        return $this->applyPlan($gateway, $this->planner->forUpdate($gateway, $original));
    }

    public function remove(Gateway $gateway): array
    {
        return $this->applyPlan($gateway, $this->planner->forDelete($gateway));
    }

    public function syncAll(iterable $gateways): void
    {
        $this->reconcile($gateways);
    }

    public function plan(iterable $gateways): array
    {
        File::ensureDirectoryExists($this->directory());

        $expected = [];
        $createOrUpdate = [];
        foreach ($gateways as $gateway) {
            $filename = 'v_'.$gateway->id.'.xml';
            $expected[] = $filename;
            if ($gateway->is_active) {
                $createOrUpdate[] = $filename;
            }
        }

        $removeOrphans = [];
        foreach (File::files($this->directory()) as $file) {
            if (! str_ends_with($file->getFilename(), '.xml')) {
                continue;
            }
            if (! in_array($file->getFilename(), $expected, true)) {
                $removeOrphans[] = $file->getFilename();
            }
        }

        return [
            'create_or_update' => $createOrUpdate,
            'remove_orphans' => $removeOrphans,
        ];
    }

    public function reconcile(iterable $gateways): array
    {
        File::ensureDirectoryExists($this->directory());
        $summary = $this->plan($gateways);

        foreach ($gateways as $gateway) {
            $filePath = $this->filePath($gateway);
            if ($gateway->is_active) {
                File::put($filePath, $this->render($gateway));
            } elseif (File::exists($filePath)) {
                File::delete($filePath);
            }
        }

        foreach ($summary['remove_orphans'] as $filename) {
            File::delete($this->directory().'/'.$filename);
        }

        $this->freeSwitch->execute('reloadxml');
        $this->freeSwitch->execute('sofia', ['profile', $this->profile(), 'rescan']);

        return $summary;
    }

    public function render(Gateway $gateway): string
    {
        $profile = $this->profile();
        $name = $this->gatewayIdentifier($gateway);
        $proxyHost = $gateway->host;
        $port = $gateway->port ?: 5060;
        $realm = $gateway->realm ?: $gateway->host;
        $proxy = str_contains($proxyHost, ':') ? $proxyHost : $proxyHost.':'.$port;
        $register = $this->shouldRegister($gateway) ? 'true' : 'false';
        $fromUser = $gateway->username ?: $gateway->name;
        $contactParams = match ($gateway->transport) {
            'tcp' => 'transport=tcp',
            'tls' => 'transport=tls',
            default => 'transport=udp',
        };

        // Build effective codec preference strings for the gateway profile
        $inboundCodecs = $gateway->inbound_codecs ?? [];
        $outboundCodecs = ! empty($gateway->preferred_codecs)
            ? $gateway->preferred_codecs
            : ($gateway->outbound_codecs ?? []);

        $params = [
            'username' => $gateway->username,
            'password' => $gateway->password,
            'realm' => $realm,
            'proxy' => $proxy,
            'register-proxy' => $proxy,
            'expire-seconds' => (string) ($gateway->expire_seconds ?: 3600),
            'retry-seconds' => (string) ($gateway->retry_seconds ?: 30),
            'caller-id-in-from' => $gateway->caller_id_in_from ? 'true' : 'false',
            'extension' => $fromUser,
            'from-user' => $fromUser,
            'from-domain' => $realm,
            'register' => $register,
            'contact-params' => $contactParams,
            'ping' => '25',
            'profile' => $profile,
        ];

        // Inject codec preferences when explicitly configured
        if (! empty($inboundCodecs)) {
            $params['inbound-codec-prefs'] = implode(',', $inboundCodecs);
        }
        if (! empty($outboundCodecs)) {
            $params['outbound-codec-prefs'] = implode(',', $outboundCodecs);
        }

        // Inject DTMF mode
        if ($gateway->dtmf_mode && $gateway->dtmf_mode !== 'rfc2833') {
            $params['dtmf-type'] = $gateway->dtmf_mode;
        }

        // Inject SRTP mode
        if ($gateway->srtp_mode && $gateway->srtp_mode !== 'none') {
            $params['rtp-secure-media'] = $gateway->srtp_mode === 'required' ? 'true' : 'optional';
        }

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

    protected function applyPlan(Gateway $gateway, GatewayLifecyclePlan $plan): array
    {
        File::ensureDirectoryExists($this->directory());
        $filePath = $this->filePath($gateway);

        if ($plan->shouldWriteFile) {
            File::put($filePath, $this->render($gateway));
        }

        if ($plan->shouldDeleteFile && File::exists($filePath)) {
            File::delete($filePath);
        }

        $results = $this->executor->execute($plan, $this->gatewayIdentifier($gateway));

        Log::info('Gateway lifecycle applied', [
            'gateway_id' => $gateway->id,
            'action' => $plan->action,
            'reason' => $plan->reason,
            'outcome' => $plan->outcome,
            'profile' => $plan->profile,
            'old_profile' => $plan->oldProfile,
            'results' => $results,
        ]);

        return [
            'action' => $plan->action,
            'reason' => $plan->reason,
            'outcome' => $plan->outcome,
            'profile' => $plan->profile,
            'old_profile' => $plan->oldProfile,
            'results' => $results,
        ];
    }

    protected function filePath(Gateway $gateway): string
    {
        return rtrim($this->directory(), '/').'/v_'.$gateway->id.'.xml';
    }

    protected function directory(): string
    {
        return config('telephony.gateway_provisioning.external_directory', storage_path('app/freeswitch/sip_profiles/external'));
    }

    protected function profile(): string
    {
        return config('telephony.gateway_provisioning.profile', 'external');
    }

    protected function gatewayIdentifier(Gateway $gateway): string
    {
        return 'v_'.$gateway->id;
    }

    protected function shouldRegister(Gateway $gateway): bool
    {
        return (bool) $gateway->register
            && filled($gateway->username)
            && filled($gateway->password)
            && filled($gateway->host)
            && filled($gateway->profile);
    }

    protected function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }
}
