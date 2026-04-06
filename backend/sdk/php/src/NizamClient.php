<?php

namespace Nizam\Sdk;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use Nizam\Sdk\Exceptions\NizamApiException;
use Nizam\Sdk\Exceptions\ValidationException;
use Nizam\Sdk\Resources\AgentResource;
use Nizam\Sdk\Resources\AuthResource;
use Nizam\Sdk\Resources\CallEventResource;
use Nizam\Sdk\Resources\CallResource;
use Nizam\Sdk\Resources\CdrResource;
use Nizam\Sdk\Resources\DidResource;
use Nizam\Sdk\Resources\ExtensionResource;
use Nizam\Sdk\Resources\QueueResource;
use Nizam\Sdk\Resources\RecordingResource;
use Nizam\Sdk\Resources\TenantResource;
use Nizam\Sdk\Resources\WebhookResource;

class NizamClient
{
    protected HttpClient $http;

    protected string $baseUrl;

    protected ?string $token;

    public function __construct(string $baseUrl, ?string $token = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->http = $this->buildHttpClient();
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        $this->http = $this->buildHttpClient();

        return $this;
    }

    public function auth(): AuthResource
    {
        return new AuthResource($this);
    }

    public function tenants(): TenantResource
    {
        return new TenantResource($this);
    }

    public function extensions(string $tenantId): ExtensionResource
    {
        return new ExtensionResource($this, $tenantId);
    }

    public function dids(string $tenantId): DidResource
    {
        return new DidResource($this, $tenantId);
    }

    public function queues(string $tenantId): QueueResource
    {
        return new QueueResource($this, $tenantId);
    }

    public function agents(string $tenantId): AgentResource
    {
        return new AgentResource($this, $tenantId);
    }

    public function webhooks(string $tenantId): WebhookResource
    {
        return new WebhookResource($this, $tenantId);
    }

    public function calls(string $tenantId): CallResource
    {
        return new CallResource($this, $tenantId);
    }

    public function recordings(string $tenantId): RecordingResource
    {
        return new RecordingResource($this, $tenantId);
    }

    public function cdrs(string $tenantId): CdrResource
    {
        return new CdrResource($this, $tenantId);
    }

    public function callEvents(string $tenantId): CallEventResource
    {
        return new CallEventResource($this, $tenantId);
    }

    /**
     * Make an HTTP request to the API.
     *
     * @throws NizamApiException|ValidationException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        try {
            $response = $this->http->request($method, $path, $options);
            $body = (string) $response->getBody();

            return json_decode($body, true) ?? [];
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            $body = json_decode((string) $e->getResponse()->getBody(), true) ?? [];

            if ($status === 422) {
                throw new ValidationException(
                    $body['message'] ?? 'Validation failed',
                    $body['errors'] ?? [],
                    $status
                );
            }

            throw new NizamApiException(
                $body['message'] ?? 'API error',
                $status
            );
        }
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, ['json' => $data]);
    }

    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, ['json' => $data]);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    protected function buildHttpClient(): HttpClient
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        return new HttpClient([
            'base_uri' => $this->baseUrl.'/',
            'headers' => $headers,
            'timeout' => 30,
        ]);
    }
}
