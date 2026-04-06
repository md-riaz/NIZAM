<?php

namespace Nizam\Sdk\Resources;

use Nizam\Sdk\NizamClient;

class AuthResource extends BaseResource
{
    public function __construct(NizamClient $client)
    {
        parent::__construct($client);
    }

    public function register(string $name, string $email, string $password): array
    {
        $response = $this->client->post('auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        if (isset($response['token'])) {
            $this->client->setToken($response['token']);
        }

        return $response;
    }

    public function login(string $email, string $password): array
    {
        $response = $this->client->post('auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        if (isset($response['token'])) {
            $this->client->setToken($response['token']);
        }

        return $response;
    }

    public function logout(): array
    {
        return $this->client->post('auth/logout');
    }

    public function me(): array
    {
        return $this->client->get('auth/me');
    }
}
