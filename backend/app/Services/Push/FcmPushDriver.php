<?php

namespace App\Services\Push;

use App\Models\EndpointBinding;
use App\Services\Push\Contracts\PushDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers push notifications via Firebase Cloud Messaging (FCM).
 *
 * Supports the FCM HTTP v1 API using a Google service account for OAuth2
 * authentication. Set the following environment variables:
 *   - FCM_PROJECT_ID              — the Firebase project ID
 *   - FCM_SERVICE_ACCOUNT_JSON    — full service-account JSON string (inline)
 *   - FCM_SERVICE_ACCOUNT_PATH    — or path to the service-account JSON file
 *
 * The service account must have the Firebase Cloud Messaging API enabled and
 * the "Firebase Cloud Messaging API Admin" role (or the messaging.messages.create
 * permission) granted on the project.
 */
class FcmPushDriver implements PushDriver
{
    protected const FCM_ENDPOINT = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    protected const OAUTH_ENDPOINT = 'https://oauth2.googleapis.com/token';

    protected const OAUTH_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Access tokens are valid for 3600 seconds; we refresh at 50 minutes.
     */
    protected const TOKEN_TTL_SECONDS = 3000;

    private ?string $cachedAccessToken = null;

    private int $tokenFetchedAt = 0;

    public function send(EndpointBinding $binding, string $pushType, array $payload): PushDeliveryResult
    {
        $token = filled($binding->push_token) ? $binding->push_token : $binding->voip_push_token;

        if (blank($token)) {
            return PushDeliveryResult::failed('no_fcm_token');
        }

        $projectId = (string) config('telephony.push.fcm.project_id', '');

        if (blank($projectId)) {
            return PushDeliveryResult::failed('fcm_config_incomplete');
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (\Throwable $e) {
            Log::error('FcmPushDriver: failed to obtain access token', ['error' => $e->getMessage()]);

            return PushDeliveryResult::failed('fcm_auth_failed: '.$e->getMessage());
        }

        $url = sprintf(self::FCM_ENDPOINT, $projectId);
        $message = $this->buildFcmMessage((string) $token, $pushType, $payload);

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post($url, ['message' => $message]);
        } catch (\Throwable $e) {
            Log::error('FcmPushDriver: HTTP request failed', [
                'endpoint_binding_id' => $binding->id,
                'error' => $e->getMessage(),
            ]);

            return PushDeliveryResult::failed('fcm_http_error: '.$e->getMessage());
        }

        if ($response->successful()) {
            $messageId = (string) ($response->json('name') ?? '');

            return PushDeliveryResult::sent('fcm', $messageId ?: null, [
                'http_status' => $response->status(),
                'project_id' => $projectId,
            ]);
        }

        $errorCode = (string) ($response->json('error.status') ?? $response->json('error.code') ?? 'unknown');
        $errorMessage = (string) ($response->json('error.message') ?? 'unknown');

        Log::warning('FcmPushDriver: FCM rejected push', [
            'endpoint_binding_id' => $binding->id,
            'status' => $response->status(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);

        return PushDeliveryResult::failed("fcm_rejected:{$errorCode}", [
            'http_status' => $response->status(),
            'fcm_error_code' => $errorCode,
            'fcm_error_message' => $errorMessage,
        ]);
    }

    protected function getAccessToken(): string
    {
        $now = time();

        if ($this->cachedAccessToken !== null && ($now - $this->tokenFetchedAt) < self::TOKEN_TTL_SECONDS) {
            return $this->cachedAccessToken;
        }

        $serviceAccount = $this->loadServiceAccount();

        if ($serviceAccount === null) {
            throw new \RuntimeException('FCM service account credentials not configured.');
        }

        $jwt = $this->buildServiceAccountJwt($serviceAccount);

        $response = Http::asForm()
            ->timeout(10)
            ->post(self::OAUTH_ENDPOINT, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'OAuth2 token exchange failed: '.(string) ($response->json('error_description') ?? $response->body())
            );
        }

        $accessToken = (string) ($response->json('access_token') ?? '');

        if (blank($accessToken)) {
            throw new \RuntimeException('OAuth2 response did not contain an access token.');
        }

        $this->cachedAccessToken = $accessToken;
        $this->tokenFetchedAt = $now;

        return $accessToken;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function loadServiceAccount(): ?array
    {
        $inline = (string) config('telephony.push.fcm.service_account_json', '');

        if (filled($inline)) {
            $decoded = json_decode($inline, true);

            return is_array($decoded) ? $decoded : null;
        }

        $path = (string) config('telephony.push.fcm.service_account_path', '');

        if (filled($path) && file_exists($path)) {
            $contents = file_get_contents($path);

            if ($contents !== false) {
                $decoded = json_decode($contents, true);

                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }

    /**
     * Build a signed JWT for Google OAuth2 service-account authentication.
     *
     * @param  array<string, mixed>  $sa
     */
    protected function buildServiceAccountJwt(array $sa): string
    {
        $now = time();

        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $claims = $this->base64UrlEncode((string) json_encode([
            'iss' => $sa['client_email'] ?? '',
            'sub' => $sa['client_email'] ?? '',
            'aud' => self::OAUTH_ENDPOINT,
            'scope' => self::OAUTH_SCOPE,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = $header.'.'.$claims;

        $privateKey = $sa['private_key'] ?? '';

        if (blank($privateKey)) {
            throw new \RuntimeException('FCM service account is missing private_key field.');
        }

        $pkey = openssl_pkey_get_private($privateKey);

        if ($pkey === false) {
            throw new \RuntimeException('Failed to load FCM service account private key: '.openssl_error_string());
        }

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $pkey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new \RuntimeException('Failed to sign FCM JWT: '.openssl_error_string());
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildFcmMessage(string $token, string $pushType, array $payload): array
    {
        // Strip internal orchestration keys that should not leave the platform
        $callData = array_diff_key($payload, array_flip([
            'provider_message_id',
        ]));

        // Encode all data values as strings — FCM data payload requires string values
        $data = [];
        foreach (['push_type' => $pushType, ...$callData] as $key => $value) {
            $data[(string) $key] = is_string($value) ? $value : (string) json_encode($value);
        }

        return [
            'token' => $token,
            'data' => $data,
            'android' => [
                'priority' => 'high',
                'ttl' => '30s',
            ],
        ];
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
