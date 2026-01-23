# KeyEnv PHP SDK

Official PHP SDK for [KeyEnv](https://keyenv.dev) - Secure secrets management for development teams.

## Requirements

- PHP 8.0 or higher

## Installation

```bash
composer require keyenv/keyenv
```

## Quick Start

```php
<?php

use KeyEnv\KeyEnv;

$client = KeyEnv::create($_ENV['KEYENV_TOKEN']);

// Load secrets into environment variables
$client->loadEnv('your-project-id', 'production');
echo $_ENV['DATABASE_URL'];
```

## Usage

### Initialize the Client

```php
use KeyEnv\KeyEnv;

// Using static factory method
$client = KeyEnv::create('your-service-token');

// Or using constructor
$client = new KeyEnv('your-service-token');

// With custom timeout (in seconds)
$client = KeyEnv::create('your-service-token', timeout: 60);
```

### Get Secrets

```php
// Get all secrets as an array of SecretWithValue objects
$secrets = $client->getSecrets('project-id', 'production');
foreach ($secrets as $secret) {
    echo "{$secret->key}={$secret->value}\n";
}

// Get secrets as a key-value associative array
$env = $client->getSecretsAsArray('project-id', 'production');
echo $env['DATABASE_URL'];

// Get a single secret
$secret = $client->getSecret('project-id', 'production', 'DATABASE_URL');
echo $secret->value;
```

### Manage Secrets

```php
// Create a new secret
$secret = $client->createSecret(
    'project-id',
    'production',
    'API_KEY',
    'sk_live_...',
    'API key for external service' // optional description
);

// Update an existing secret
$secret = $client->updateSecret(
    'project-id',
    'production',
    'API_KEY',
    'sk_live_new...'
);

// Set a secret (creates or updates)
$secret = $client->setSecret(
    'project-id',
    'production',
    'API_KEY',
    'sk_live_...'
);

// Delete a secret
$client->deleteSecret('project-id', 'production', 'OLD_KEY');
```

### Load into Environment

```php
// Load secrets into $_ENV and putenv()
$count = $client->loadEnv('project-id', 'production');
echo "Loaded {$count} secrets\n";
echo $_ENV['DATABASE_URL'];
echo getenv('DATABASE_URL');
```

### Generate .env File

```php
$envContent = $client->generateEnvFile('project-id', 'production');
file_put_contents('.env', $envContent);
```

### List Environments

```php
$environments = $client->listEnvironments('project-id');
foreach ($environments as $env) {
    echo "{$env->name}\n";
}
```

### Validate Token

```php
$user = $client->validateToken();
echo "Authenticated as: " . ($user['email'] ?? $user['id']) . "\n";
```

## Error Handling

```php
use KeyEnv\KeyEnv;
use KeyEnv\KeyEnvException;

try {
    $secret = $client->getSecret('project-id', 'production', 'MISSING_KEY');
} catch (KeyEnvException $e) {
    echo "Error {$e->getStatusCode()}: {$e->getMessage()}\n";

    if ($e->isNotFound()) {
        echo "Secret not found\n";
    } elseif ($e->isUnauthorized()) {
        echo "Invalid token\n";
    } elseif ($e->isTimeout()) {
        echo "Request timed out\n";
    }

    // Additional error details
    $code = $e->getErrorCode();
    $details = $e->getDetails();
}
```

## API Reference

### `KeyEnv::create($token, $timeout = 30)`

Create a new KeyEnv client.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$token` | `string` | Yes | - | Service token |
| `$timeout` | `int` | No | `30` | Request timeout (seconds) |

### Methods

| Method | Description |
|--------|-------------|
| `getSecrets($projectId, $environment)` | Get all secrets with values |
| `getSecretsAsArray($projectId, $environment)` | Get secrets as key-value array |
| `getSecret($projectId, $environment, $key)` | Get a single secret |
| `listSecrets($projectId, $environment)` | List secret keys (no values) |
| `createSecret($projectId, $environment, $key, $value, $description)` | Create a secret |
| `updateSecret($projectId, $environment, $key, $value, $description)` | Update a secret |
| `setSecret($projectId, $environment, $key, $value, $description)` | Create or update a secret |
| `deleteSecret($projectId, $environment, $key)` | Delete a secret |
| `listEnvironments($projectId)` | List environments in a project |
| `loadEnv($projectId, $environment)` | Load secrets into environment |
| `generateEnvFile($projectId, $environment)` | Generate .env file content |
| `validateToken()` | Validate token and get user info |

## Types

### `SecretWithValue`

```php
class SecretWithValue {
    public readonly string $id;
    public readonly string $environmentId;
    public readonly string $key;
    public readonly string $type;
    public readonly int $version;
    public readonly string $value;
    public readonly ?string $inheritedFrom;
    public readonly ?string $description;
    public readonly ?string $createdAt;
    public readonly ?string $updatedAt;
}
```

### `Secret`

Same as `SecretWithValue` but without `$value` and `$inheritedFrom`.

### `Environment`

```php
class Environment {
    public readonly string $id;
    public readonly string $projectId;
    public readonly string $name;
    public readonly ?string $inheritsFrom;
    public readonly ?string $createdAt;
}
```

### `KeyEnvException`

```php
class KeyEnvException extends Exception {
    public function getStatusCode(): int;
    public function getErrorCode(): ?string;
    public function getDetails(): array;
    public function isNotFound(): bool;
    public function isUnauthorized(): bool;
    public function isTimeout(): bool;
}
```

## License

MIT
