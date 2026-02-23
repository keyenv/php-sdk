<?php

declare(strict_types=1);

namespace KeyEnv;

use KeyEnv\Types\BulkImportResult;
use KeyEnv\Types\Environment;
use KeyEnv\Types\EnvironmentPermission;
use KeyEnv\Types\ProjectDefault;
use KeyEnv\Types\Secret;
use KeyEnv\Types\SecretHistory;
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
 * // Export all secrets for an environment
 * $secrets = $client->exportSecrets('project-id', 'production');
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

    // ============================================================================
    // User / Token
    // ============================================================================

    /**
     * Get current user information.
     *
     * @return array<string, mixed> User information
     * @throws KeyEnvException
     */
    public function getCurrentUser(): array
    {
        $data = $this->request('GET', '/api/v1/users/me');
        return $data['data'] ?? $data;
    }

    /**
     * Validate the token and get current user info.
     *
     * @return array<string, mixed> User information
     * @throws KeyEnvException
     */
    public function validateToken(): array
    {
        return $this->getCurrentUser();
    }

    // ============================================================================
    // Projects
    // ============================================================================

    /**
     * List all accessible projects.
     *
     * @return array<string, mixed>[] Array of projects
     * @throws KeyEnvException
     */
    public function listProjects(): array
    {
        $data = $this->request('GET', '/api/v1/projects');
        return $data['data'] ?? [];
    }

    /**
     * Get a project by ID.
     *
     * @param string $projectId The project ID or slug
     * @return array<string, mixed> Project with environments
     * @throws KeyEnvException
     */
    public function getProject(string $projectId): array
    {
        $data = $this->request('GET', "/api/v1/projects/{$projectId}");
        return $data['data'] ?? $data;
    }

    /**
     * Create a new project.
     *
     * @param string $teamId The team ID
     * @param string $name The project name
     * @return array<string, mixed> The created project
     * @throws KeyEnvException
     */
    public function createProject(string $teamId, string $name): array
    {
        $data = $this->request('POST', '/api/v1/projects', [
            'team_id' => $teamId,
            'name' => $name,
        ]);
        return $data['data'] ?? $data;
    }

    /**
     * Delete a project.
     *
     * @param string $projectId The project ID or slug
     * @throws KeyEnvException
     */
    public function deleteProject(string $projectId): void
    {
        $this->request('DELETE', "/api/v1/projects/{$projectId}");
    }

    // ============================================================================
    // Environments
    // ============================================================================

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
        foreach ($data['data'] ?? [] as $envData) {
            $environments[] = Environment::fromArray($envData);
        }

        return $environments;
    }

    /**
     * Create a new environment.
     *
     * @param string $projectId The project ID
     * @param string $name The environment name
     * @param string|null $inheritsFrom Optional parent environment name to inherit secrets from
     * @return Environment The created environment
     * @throws KeyEnvException
     */
    public function createEnvironment(string $projectId, string $name, ?string $inheritsFrom = null): Environment
    {
        $payload = ['name' => $name];
        if ($inheritsFrom !== null) {
            $payload['inherits_from'] = $inheritsFrom;
        }

        $data = $this->request('POST', "/api/v1/projects/{$projectId}/environments", $payload);
        return Environment::fromArray($data['data'] ?? $data);
    }

    /**
     * Delete an environment.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @throws KeyEnvException
     */
    public function deleteEnvironment(string $projectId, string $environment): void
    {
        $this->request('DELETE', "/api/v1/projects/{$projectId}/environments/{$environment}");
    }

    // ============================================================================
    // Secrets
    // ============================================================================

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
        foreach ($data['data'] ?? [] as $secretData) {
            $secrets[] = Secret::fromArray($secretData);
        }

        return $secrets;
    }

    /**
     * Export all secrets for a project environment with their decrypted values.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name (e.g., 'production', 'development')
     * @return SecretWithValue[] Array of secrets with their values
     * @throws KeyEnvException
     */
    public function exportSecrets(string $projectId, string $environment): array
    {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/secrets/export";
        $data = $this->request('GET', $path);

        $secrets = [];
        foreach ($data['data'] ?? [] as $secretData) {
            $secrets[] = SecretWithValue::fromArray($secretData);
        }

        return $secrets;
    }

    /**
     * Export secrets as an associative array (key => value).
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @return array<string, string> Associative array of secret key => value
     * @throws KeyEnvException
     */
    public function exportSecretsAsArray(string $projectId, string $environment): array
    {
        $secrets = $this->exportSecrets($projectId, $environment);
        $result = [];
        foreach ($secrets as $secret) {
            $result[$secret->key] = $secret->value;
        }
        return $result;
    }

    /**
     * Get all secrets for a project environment with their decrypted values.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name (e.g., 'production', 'development')
     * @return SecretWithValue[] Array of secrets with their values
     * @throws KeyEnvException
     *
     * @deprecated Use exportSecrets() instead. This method will be removed in a future version.
     */
    public function getSecrets(string $projectId, string $environment): array
    {
        return $this->exportSecrets($projectId, $environment);
    }

    /**
     * Get secrets as an associative array (key => value).
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @return array<string, string> Associative array of secret key => value
     * @throws KeyEnvException
     *
     * @deprecated Use exportSecretsAsArray() instead. This method will be removed in a future version.
     */
    public function getSecretsAsArray(string $projectId, string $environment): array
    {
        return $this->exportSecretsAsArray($projectId, $environment);
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

        return SecretWithValue::fromArray($data['data'] ?? $data);
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

        return Secret::fromArray($data['data'] ?? $data);
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

        return Secret::fromArray($data['data'] ?? $data);
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
     * Get secret version history.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param string $key The secret key name
     * @return SecretHistory[] Array of history entries
     * @throws KeyEnvException
     */
    public function getSecretHistory(string $projectId, string $environment, string $key): array
    {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/secrets/{$key}/history";
        $data = $this->request('GET', $path);

        $history = [];
        foreach ($data['data'] ?? [] as $entry) {
            $history[] = SecretHistory::fromArray($entry);
        }

        return $history;
    }

    /**
     * Bulk import secrets.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param array<array{key: string, value: string, description?: string}> $secrets Array of secrets to import
     * @param array{overwrite?: bool} $options Import options
     * @return BulkImportResult The import result
     * @throws KeyEnvException
     *
     * @example
     * ```php
     * $result = $client->bulkImport('project-id', 'development', [
     *     ['key' => 'DATABASE_URL', 'value' => 'postgres://...'],
     *     ['key' => 'API_KEY', 'value' => 'sk_...'],
     * ], ['overwrite' => true]);
     * ```
     */
    public function bulkImport(
        string $projectId,
        string $environment,
        array $secrets,
        array $options = []
    ): BulkImportResult {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/secrets/bulk";
        $payload = [
            'secrets' => $secrets,
            'overwrite' => $options['overwrite'] ?? false,
        ];

        $data = $this->request('POST', $path, $payload);

        return BulkImportResult::fromArray($data['data'] ?? $data);
    }

    // ============================================================================
    // Environment Permissions
    // ============================================================================

    /**
     * List all permissions for an environment.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @return EnvironmentPermission[] Array of environment permissions
     * @throws KeyEnvException
     *
     * @example
     * ```php
     * $permissions = $client->listPermissions('project-id', 'production');
     * foreach ($permissions as $perm) {
     *     echo "{$perm->userEmail}: {$perm->role}\n";
     * }
     * ```
     */
    public function listPermissions(string $projectId, string $environment): array
    {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/permissions";
        $data = $this->request('GET', $path);

        $permissions = [];
        foreach ($data['data'] ?? [] as $permData) {
            $permissions[] = EnvironmentPermission::fromArray($permData);
        }

        return $permissions;
    }

    /**
     * Set a user's permission for an environment.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param string $userId The user ID to set permission for
     * @param string $role The permission role ('none', 'read', 'write', or 'admin')
     * @return EnvironmentPermission The created or updated permission
     * @throws KeyEnvException
     *
     * @example
     * ```php
     * $permission = $client->setPermission('project-id', 'production', 'user-id', 'write');
     * echo "Set {$permission->userEmail} to {$permission->role}\n";
     * ```
     */
    public function setPermission(
        string $projectId,
        string $environment,
        string $userId,
        string $role
    ): EnvironmentPermission {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/permissions/{$userId}";
        $data = $this->request('PUT', $path, ['role' => $role]);

        return EnvironmentPermission::fromArray($data['data'] ?? $data);
    }

    /**
     * Delete a user's permission for an environment.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param string $userId The user ID to delete permission for
     * @throws KeyEnvException
     *
     * @example
     * ```php
     * $client->deletePermission('project-id', 'production', 'user-id');
     * ```
     */
    public function deletePermission(string $projectId, string $environment, string $userId): void
    {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/permissions/{$userId}";
        $this->request('DELETE', $path);
    }

    /**
     * Bulk set permissions for multiple users in an environment.
     *
     * @param string $projectId The project ID
     * @param string $environment The environment name
     * @param array<array{user_id: string, role: string}> $permissions Array of user permissions to set
     * @return EnvironmentPermission[] Array of created or updated permissions
     * @throws KeyEnvException
     *
     * @example
     * ```php
     * $permissions = $client->bulkSetPermissions('project-id', 'production', [
     *     ['user_id' => 'user-1', 'role' => 'write'],
     *     ['user_id' => 'user-2', 'role' => 'read'],
     * ]);
     * ```
     */
    public function bulkSetPermissions(string $projectId, string $environment, array $permissions): array
    {
        $path = "/api/v1/projects/{$projectId}/environments/{$environment}/permissions";
        $data = $this->request('PUT', $path, ['permissions' => $permissions]);

        $result = [];
        foreach ($data['data'] ?? [] as $permData) {
            $result[] = EnvironmentPermission::fromArray($permData);
        }

        return $result;
    }

    /**
     * Get the current user's permissions for all environments in a project.
     *
     * @param string $projectId The project ID
     * @return array<string, mixed> The user's permissions and team admin status
     * @throws KeyEnvException
     *
     * @example
     * ```php
     * $result = $client->getMyPermissions('project-id');
     * foreach ($result['permissions'] as $perm) {
     *     echo "{$perm['environment_name']}: {$perm['role']}\n";
     * }
     * ```
     */
    public function getMyPermissions(string $projectId): array
    {
        return $this->request('GET', "/api/v1/projects/{$projectId}/my-permissions");
    }

    // ============================================================================
    // Project Defaults
    // ============================================================================

    /**
     * Get default permission settings for a project's environments.
     *
     * @param string $projectId The project ID
     * @return ProjectDefault[] Array of project default permissions
     * @throws KeyEnvException
     *
     * @example
     * ```php
     * $defaults = $client->getProjectDefaults('project-id');
     * foreach ($defaults as $default) {
     *     echo "{$default->environmentName}: {$default->defaultRole}\n";
     * }
     * ```
     */
    public function getProjectDefaults(string $projectId): array
    {
        $data = $this->request('GET', "/api/v1/projects/{$projectId}/permissions/defaults");

        $defaults = [];
        foreach ($data['data'] ?? [] as $defaultData) {
            $defaults[] = ProjectDefault::fromArray($defaultData);
        }

        return $defaults;
    }

    /**
     * Set default permission settings for a project's environments.
     *
     * @param string $projectId The project ID
     * @param array<array{environment_name: string, default_role: string}> $defaults Array of default permissions to set
     * @return ProjectDefault[] Array of updated project default permissions
     * @throws KeyEnvException
     *
     * @example
     * ```php
     * $defaults = $client->setProjectDefaults('project-id', [
     *     ['environment_name' => 'development', 'default_role' => 'write'],
     *     ['environment_name' => 'production', 'default_role' => 'read'],
     * ]);
     * ```
     */
    public function setProjectDefaults(string $projectId, array $defaults): array
    {
        $data = $this->request('PUT', "/api/v1/projects/{$projectId}/permissions/defaults", [
            'defaults' => $defaults,
        ]);

        $result = [];
        foreach ($data['data'] ?? [] as $defaultData) {
            $result[] = ProjectDefault::fromArray($defaultData);
        }

        return $result;
    }

    // ============================================================================
    // Utility Methods
    // ============================================================================

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
        $secrets = $this->exportSecrets($projectId, $environment);
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
        $secrets = $this->exportSecrets($projectId, $environment);
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

    // ============================================================================
    // HTTP Client
    // ============================================================================

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
            // API error format: {"error": {"code": "...", "message": "...", "details": {...}}}
            $error = $data['error'] ?? [];
            if (is_array($error)) {
                $message = $error['message'] ?? 'Unknown error';
                $code = $error['code'] ?? null;
                $details = $error['details'] ?? [];
            } else {
                // Fallback for simple error format: {"error": "message"}
                $message = is_string($error) ? $error : 'Unknown error';
                $code = $data['code'] ?? null;
                $details = $data['details'] ?? [];
            }
            throw new KeyEnvException($message, $statusCode, $code, $details);
        }

        return $data ?? [];
    }
}
