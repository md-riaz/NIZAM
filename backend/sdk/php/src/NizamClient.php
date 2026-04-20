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
use Nizam\Sdk\Resources\OrganizationResource;
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

    public function organizations(): OrganizationResource
    {
        return new OrganizationResource($this);
    }

    public function extensions(string $organizationId): ExtensionResource
    {
        return new ExtensionResource($this, $organizationId);
    }

    public function dids(string $organizationId): DidResource
    {
        return new DidResource($this, $organizationId);
    }

    public function queues(string $organizationId): QueueResource
    {
        return new QueueResource($this, $organizationId);
    }

    public function agents(string $organizationId): AgentResource
    {
        return new AgentResource($this, $organizationId);
    }

    public function webhooks(string $organizationId): WebhookResource
    {
        return new WebhookResource($this, $organizationId);
    }

    public function calls(string $organizationId): CallResource
    {
        return new CallResource($this, $organizationId);
    }

    public function recordings(string $organizationId): RecordingResource
    {
        return new RecordingResource($this, $organizationId);
    }

    public function cdrs(string $organizationId): CdrResource
    {
        return new CdrResource($this, $organizationId);
    }

    public function callEvents(string $organizationId): CallEventResource
    {
        return new CallEventResource($this, $organizationId);
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
