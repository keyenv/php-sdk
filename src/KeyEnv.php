<?php

declare(strict_types=1);

namespace KeyEnv;

use KeyEnv\Types\Environment;
use KeyEnv\Types\Secret;
use KeyEnv\Types\SecretWithValue;

/**
 * KeyEnv API client for managing secrets.
 *
 * @example
 * ```php
 * use KeyEnv\KeyEnv;
 *
 * $client = KeyEnv::create($_ENV['KEYENV_TOKEN']);
 *
 * // Get all secrets for an environment
 * $secrets = $client->getSecrets('project-id', 'production');
 *
 * // Get a single secret
 * $secret = $client->getSecret('project-id', 'production', 'DATABASE_URL');
 * echo $secret->value;
 * ```
 */
class KeyEnv
{
    private const DEFAULT_BASE_URL = 'https://api.keyenv.dev';
    private const DEFAULT_TIMEOUT = 30;
    private const USER_AGENT = 'keyenv-php/1.0.0';

    private string $token;
    private string $baseUrl;
    private int $timeout;

    /**
     * Create a new KeyEnv client.
     *
     * @param string $token Service token for authentication
     * @param int $timeout Request timeout in seconds (default: 30)
     * @param string|null $baseUrl Custom API base URL. Also configurable via KEYENV_API_URL env var.
     */
    public function __construct(string $token, int $timeout = self::DEFAULT_TIMEOUT, ?string $baseUrl = null)
    {
        if (empty($token)) {
            throw new \InvalidArgumentException('KeyEnv token is required');
        }

        $this->token = $token;
        $this->timeout = $timeout;

        if ($baseUrl !== null) {
            $this->baseUrl = rtrim($baseUrl, '/');
        } elseif ($envUrl = getenv('KEYENV_API_URL')) {
            $this->baseUrl = rtrim($envUrl, '/');
        } else {
            $this->baseUrl = self::DEFAULT_BASE_URL;
        }
    }

    /**
     * Static factory method to create a new KeyEnv client.
     *
     * @param string $token Service token for authentication
     * @param int $timeout Request timeout in seconds (default: 30)
     * @param string|null $baseUrl Custom API base URL. Also configurable via KEYENV_API_URL env var.
     */
    public static function create(string $token, int $timeout = self::DEFAULT_TIMEOUT, ?string $baseUrl = null): self
    {
        return new self($token, $timeout, $baseUrl);
    }

    /**
     * Get all secrets for a project environment with their decrypted values.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name (e.g., 'production', 'development')
     * @return SecretWithValue[] Array of secrets with their values
     * @throws KeyEnvException
     */
    public function getSecrets(string $projectId, string $environment): array
    {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/secrets/export";
        $data = $this->request('GET', $path);

        $secrets = [];
        foreach ($data['secrets'] ?? [] as $secretData) {
            $secrets[] = SecretWithValue::fromArray($secretData);
        }

        return $secrets;
    }

    /**
     * Get secrets as an associative array (key => value).
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @return array<string, string> Associative array of secret key => value
     * @throws KeyEnvException
     */
    public function getSecretsAsArray(string $projectId, string $environment): array
    {
        $secrets = $this->getSecrets($projectId, $environment);
        $result = [];
        foreach ($secrets as $secret) {
            $result[$secret->key] = $secret->value;
        }
        return $result;
    }

    /**
     * Get a single secret with its decrypted value.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param string $key The secret key name
     * @return SecretWithValue The secret with its value
     * @throws KeyEnvException
     */
    public function getSecret(string $projectId, string $environment, string $key): SecretWithValue
    {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/secrets/{$key}";
        $data = $this->request('GET', $path);

        return SecretWithValue::fromArray($data['secret'] ?? $data);
    }

    /**
     * List secret keys in an environment (without values).
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @return Secret[] Array of secrets (without values)
     * @throws KeyEnvException
     */
    public function listSecrets(string $projectId, string $environment): array
    {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/secrets";
        $data = $this->request('GET', $path);

        $secrets = [];
        foreach ($data['secrets'] ?? [] as $secretData) {
            $secrets[] = Secret::fromArray($secretData);
        }

        return $secrets;
    }

    /**
     * Create a new secret.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param string $key The secret key name
     * @param string $value The secret value
     * @param string|null $description Optional description
     * @return Secret The created secret
     * @throws KeyEnvException
     */
    public function createSecret(
        string $projectId,
        string $environment,
        string $key,
        string $value,
        ?string $description = null
    ): Secret {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/secrets";
        $payload = ['key' => $key, 'value' => $value];
        if ($description !== null) {
            $payload['description'] = $description;
        }

        $data = $this->request('POST', $path, $payload);

        return Secret::fromArray($data['secret'] ?? $data);
    }

    /**
     * Update an existing secret.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param string $key The secret key name
     * @param string $value The new secret value
     * @param string|null $description Optional description
     * @return Secret The updated secret
     * @throws KeyEnvException
     */
    public function updateSecret(
        string $projectId,
        string $environment,
        string $key,
        string $value,
        ?string $description = null
    ): Secret {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/secrets/{$key}";
        $payload = ['value' => $value];
        if ($description !== null) {
            $payload['description'] = $description;
        }

        $data = $this->request('PUT', $path, $payload);

        return Secret::fromArray($data['secret'] ?? $data);
    }

    /**
     * Set a secret (create or update).
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param string $key The secret key name
     * @param string $value The secret value
     * @param string|null $description Optional description
     * @return Secret The created or updated secret
     * @throws KeyEnvException
     */
    public function setSecret(
        string $projectId,
        string $environment,
        string $key,
        string $value,
        ?string $description = null
    ): Secret {
        try {
            return $this->updateSecret($projectId, $environment, $key, $value, $description);
        } catch (KeyEnvException $e) {
            if ($e->isNotFound()) {
                return $this->createSecret($projectId, $environment, $key, $value, $description);
            }
            throw $e;
        }
    }

    /**
     * Delete a secret.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param string $key The secret key name
     * @throws KeyEnvException
     */
    public function deleteSecret(string $projectId, string $environment, string $key): void
    {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/secrets/{$key}";
        $this->request('DELETE', $path);
    }

    /**
     * List environments in a project.
     *
     * @param string $projectId The project ID
     * @return Environment[] Array of environments
     * @throws KeyEnvException
     */
    public function listEnvironments(string $projectId): array
    {
        $path = "/api/v1/projects/{$projectId}/environments";
        $data = $this->request('GET', $path);

        $environments = [];
        foreach ($data['environments'] ?? [] as $envData) {
            $environments[] = Environment::fromArray($envData);
        }

        return $environments;
    }

    /**
     * Load secrets into environment variables ($_ENV and putenv).
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @return int Number of secrets loaded
     * @throws KeyEnvException
     */
    public function loadEnv(string $projectId, string $environment): int
    {
        $secrets = $this->getSecrets($projectId, $environment);
        foreach ($secrets as $secret) {
            $_ENV[$secret->key] = $secret->value;
            putenv("{$secret->key}={$secret->value}");
        }
        return count($secrets);
    }

    /**
     * Generate .env file content from secrets.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @return string The .env file content
     * @throws KeyEnvException
     */
    public function generateEnvFile(string $projectId, string $environment): string
    {
        $secrets = $this->getSecrets($projectId, $environment);
        $lines = [
            '# Generated by KeyEnv',
            "# Environment: {$environment}",
            '# Generated at: ' . gmdate('Y-m-d\TH:i:s\Z'),
            '',
        ];

        foreach ($secrets as $secret) {
            $value = $secret->value;
            if (
                str_contains($value, "\n") ||
                str_contains($value, '"') ||
                str_contains($value, "'") ||
                str_contains($value, ' ')
            ) {
                $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
                $lines[] = "{$secret->key}=\"{$escaped}\"";
            } else {
                $lines[] = "{$secret->key}={$value}";
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Validate the token and get current user info.
     *
     * @return array<string, mixed> User information
     * @throws KeyEnvException
     */
    public function validateToken(): array
    {
        return $this->request('GET', '/api/v1/users/me');
    }

    /**
     * List all accessible projects.
     *
     * @return array<string, mixed>[] Array of projects
     * @throws KeyEnvException
     */
    public function listProjects(): array
    {
        $data = $this->request('GET', '/api/v1/projects');
        return $data['projects'] ?? [];
    }

    /**
     * Get current user information.
     *
     * @return array<string, mixed> User information
     * @throws KeyEnvException
     */
    public function getCurrentUser(): array
    {
        return $this->request('GET', '/api/v1/users/me');
    }

    /**
     * Make an HTTP request to the API using cURL.
     *
     * @param string $method HTTP method
     * @param string $path API path
     * @param array<string, mixed>|null $body Request body
     * @return array<string, mixed> Response data
     * @throws KeyEnvException
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        if (!extension_loaded('curl')) {
            throw new KeyEnvException('cURL extension is required', 0);
        }

        $url = $this->baseUrl . $path;

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ' . self::USER_AGENT,
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        // Set HTTP method and body
        switch (strtoupper($method)) {
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                }
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                }
        }

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        // Handle cURL errors
        if ($response === false || $curlErrno !== 0) {
            if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
                throw new KeyEnvException('Request timeout', 408);
            }
            throw new KeyEnvException(
                $curlError ?: 'Network error',
                0
            );
        }

        // Handle 204 No Content
        if ($statusCode === 204) {
            return [];
        }

        // Parse JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE && $response !== '') {
            $data = ['error' => $response];
        }

        // Handle error responses
        if ($statusCode >= 400) {
            throw new KeyEnvException(
                $data['error'] ?? 'Unknown error',
                $statusCode,
                $data['code'] ?? null,
                $data['details'] ?? []
            );
        }

        return $data ?? [];
    }
}
