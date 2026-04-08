<?php

namespace App\Services\Push;

use App\Models\EndpointBinding;
use App\Services\Push\Contracts\PushDriver;
use Illuminate\Support\Facades\Log;

/**
 * Delivers iOS VoIP push notifications via Apple Push Notification service (APNs).
 *
 * Uses the APNs HTTP/2 provider API with JWT (token-based) authentication.
 * Requires an APNs Auth Key (.p8 file) from Apple Developer console and:
 *   - APNS_KEY_ID       — the 10-character key identifier
 *   - APNS_TEAM_ID      — the 10-character Apple Developer Team ID
 *   - APNS_PRIVATE_KEY  — PEM-encoded EC private key contents (or set APNS_PRIVATE_KEY_PATH)
 *   - APNS_BUNDLE_ID    — the app bundle ID (the .voip topic is appended automatically)
 *   - APNS_PRODUCTION   — true for production, false for sandbox
 */
class ApnsPushDriver implements PushDriver
{
    protected const PROD_HOST = 'https://api.push.apple.com';

    protected const SANDBOX_HOST = 'https://api.sandbox.push.apple.com';

    protected const APNS_PORT = 443;

    /**
     * JWT tokens are valid for up to 60 minutes; we refresh at 50 minutes.
     */
    protected const JWT_TTL_SECONDS = 3000;

    private ?string $cachedJwt = null;

    private int $jwtIssuedAt = 0;

    public function send(EndpointBinding $binding, string $pushType, array $payload): PushDeliveryResult
    {
        $token = filled($binding->voip_push_token) ? $binding->voip_push_token : $binding->push_token;

        if (blank($token)) {
            return PushDeliveryResult::failed('no_apns_token');
        }

        $keyId = (string) config('telephony.push.apns.key_id', '');
        $teamId = (string) config('telephony.push.apns.team_id', '');
        $bundleId = (string) config('telephony.push.apns.bundle_id', '');
        $production = (bool) config('telephony.push.apns.production', true);

        if (blank($keyId) || blank($teamId) || blank($bundleId)) {
            return PushDeliveryResult::failed('apns_config_incomplete');
        }

        $privateKey = $this->loadPrivateKey();

        if ($privateKey === null) {
            return PushDeliveryResult::failed('apns_private_key_unavailable');
        }

        try {
            $jwt = $this->buildJwt($keyId, $teamId, $privateKey);
        } catch (\Throwable $e) {
            Log::error('ApnsPushDriver: JWT generation failed', ['error' => $e->getMessage()]);

            return PushDeliveryResult::failed('apns_jwt_generation_failed: '.$e->getMessage());
        }

        $host = $production ? self::PROD_HOST : self::SANDBOX_HOST;
        $url = "{$host}/3/device/{$token}";

        // VoIP pushes use the .voip topic suffix
        $topic = $bundleId.'.voip';
        $apnsBody = $this->buildApnsBody($pushType, $payload);

        try {
            [$statusCode, $responseBody] = $this->sendWithCurl($url, $jwt, $topic, $apnsBody);
        } catch (\Throwable $e) {
            Log::error('ApnsPushDriver: HTTP request failed', [
                'endpoint_binding_id' => $binding->id,
                'error' => $e->getMessage(),
            ]);

            return PushDeliveryResult::failed('apns_http_error: '.$e->getMessage());
        }

        if ($statusCode === 200) {
            return PushDeliveryResult::sent('apns_voip', null, [
                'http_status' => $statusCode,
                'production' => $production,
                'topic' => $topic,
            ]);
        }

        $decoded = json_decode($responseBody, true);
        $reason = is_array($decoded) ? ($decoded['reason'] ?? 'unknown') : 'unknown';

        Log::warning('ApnsPushDriver: APNs rejected push', [
            'endpoint_binding_id' => $binding->id,
            'status' => $statusCode,
            'reason' => $reason,
        ]);

        return PushDeliveryResult::failed("apns_rejected:{$reason}", [
            'http_status' => $statusCode,
            'apns_reason' => $reason,
            'production' => $production,
        ]);
    }

    protected function loadPrivateKey(): ?string
    {
        $keyContents = (string) config('telephony.push.apns.private_key', '');

        if (filled($keyContents)) {
            return $keyContents;
        }

        $keyPath = (string) config('telephony.push.apns.private_key_path', '');

        if (filled($keyPath) && file_exists($keyPath)) {
            $contents = file_get_contents($keyPath);

            return $contents !== false ? $contents : null;
        }

        return null;
    }

    protected function buildJwt(string $keyId, string $teamId, string $privateKey): string
    {
        $now = time();

        // Reuse cached JWT if still within TTL
        if ($this->cachedJwt !== null && ($now - $this->jwtIssuedAt) < self::JWT_TTL_SECONDS) {
            return $this->cachedJwt;
        }

        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'ES256',
            'kid' => $keyId,
        ]));

        $claims = $this->base64UrlEncode((string) json_encode([
            'iss' => $teamId,
            'iat' => $now,
        ]));

        $signingInput = $header.'.'.$claims;

        $pkey = openssl_pkey_get_private($privateKey);

        if ($pkey === false) {
            throw new \RuntimeException('Failed to load APNs private key: '.openssl_error_string());
        }

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $pkey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new \RuntimeException('Failed to sign APNs JWT: '.openssl_error_string());
        }

        $jwt = $signingInput.'.'.$this->base64UrlEncode($signature);

        $this->cachedJwt = $jwt;
        $this->jwtIssuedAt = $now;

        return $jwt;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildApnsBody(string $pushType, array $payload): array
    {
        // Strip internal orchestration keys that should not leave the platform
        $callData = array_diff_key($payload, array_flip([
            'provider_message_id',
        ]));

        return [
            'aps' => [
                'alert' => '', // VoIP pushes typically have empty alert; CallKit handles UI
                'content-available' => 1,
            ],
            'nizam' => [
                'push_type' => $pushType,
                ...$callData,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: int, 1: string}
     */
    protected function sendWithCurl(string $url, string $jwt, string $topic, array $body): array
    {
        $json = (string) json_encode($body);

        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }

        $headers = [
            'authorization: bearer '.$jwt,
            'apns-push-type: voip',
            'apns-topic: '.$topic,
            'apns-expiration: 0',
            'apns-priority: 10',
            'content-type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('curl request failed: '.$curlError);
        }

        return [$statusCode, (string) $response];
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
