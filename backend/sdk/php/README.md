# NIZAM PHP SDK

Official PHP SDK for the NIZAM Open Communications Control Platform API.

## Requirements

- PHP 8.2+
- Guzzle 7.x

## Installation

```bash
composer require nizam/php-sdk
```

## Quick Start

```php
use Nizam\Sdk\NizamClient;

$client = new NizamClient('http://localhost/api', 'your-api-token');

// List tenants
$tenants = $client->tenants()->list();

// Create a tenant
$tenant = $client->tenants()->create([
    'name' => 'Acme Corp',
    'domain' => 'acme.example.com',
    'max_extensions' => 50,
]);

// List extensions for a tenant
$extensions = $client->extensions($tenantId)->list();

// Create an extension
$extension = $client->extensions($tenantId)->create([
    'extension' => '1001',
    'password' => 'securePass123!',
    'directory_first_name' => 'John',
    'directory_last_name' => 'Doe',
]);

// Manage queues
$queues = $client->queues($tenantId)->list();
$metrics = $client->queues($tenantId)->realtimeMetrics($queueId);

// Webhooks
$webhooks = $client->webhooks($tenantId)->list();
$webhook = $client->webhooks($tenantId)->create([
    'url' => 'https://hooks.example.com/nizam',
    'events' => ['call.created', 'call.hangup'],
    'secret' => 'my-secret',
]);
```

## Authentication

The SDK uses Laravel Sanctum token-based authentication. Obtain a token via the login endpoint or create one via the API tokens endpoint.

```php
$client = new NizamClient('http://localhost/api');
$response = $client->auth()->login('user@example.com', 'password');
// Token is automatically set on the client
```

## Error Handling

```php
use Nizam\Sdk\Exceptions\NizamApiException;
use Nizam\Sdk\Exceptions\ValidationException;

try {
    $tenant = $client->tenants()->create([...]);
} catch (ValidationException $e) {
    // 422 - validation errors
    $errors = $e->getErrors();
} catch (NizamApiException $e) {
    // Other API errors (401, 403, 404, 500)
    $status = $e->getStatusCode();
    $message = $e->getMessage();
}
```

## Available Resources

| Resource | Methods |
|----------|---------|
| `auth()` | `login()`, `register()`, `logout()`, `me()` |
| `tenants()` | `list()`, `create()`, `get()`, `update()`, `delete()`, `settings()` |
| `extensions($tenantId)` | `list()`, `create()`, `get()`, `update()`, `delete()` |
| `dids($tenantId)` | `list()`, `create()`, `get()`, `update()`, `delete()` |
| `queues($tenantId)` | `list()`, `create()`, `get()`, `update()`, `delete()`, `realtimeMetrics()` |
| `agents($tenantId)` | `list()`, `create()`, `get()`, `update()`, `delete()`, `changeState()` |
| `webhooks($tenantId)` | `list()`, `create()`, `get()`, `update()`, `delete()`, `deliveryStats()` |
| `calls($tenantId)` | `originate()`, `hangup()`, `transfer()`, `hold()` |
| `recordings($tenantId)` | `list()`, `get()`, `download()`, `delete()` |
| `cdrs($tenantId)` | `list()`, `get()`, `export()` |
| `callEvents($tenantId)` | `list()`, `trace()`, `replay()` |

## License

MIT
