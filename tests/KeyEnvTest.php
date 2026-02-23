<?php

declare(strict_types=1);

namespace KeyEnv\Tests;

use KeyEnv\KeyEnv;
use KeyEnv\KeyEnvException;
use KeyEnv\Types\BulkImportResult;
use KeyEnv\Types\Environment;
use KeyEnv\Types\EnvironmentPermission;
use KeyEnv\Types\ProjectDefault;
use KeyEnv\Types\Secret;
use KeyEnv\Types\SecretHistory;
use KeyEnv\Types\SecretWithValue;
use PHPUnit\Framework\TestCase;

class KeyEnvTest extends TestCase
{
    public function testCreateRequiresToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('KeyEnv token is required');

        KeyEnv::create('');
    }

    public function testCreateWithToken(): void
    {
        $client = KeyEnv::create('test-token');
        $this->assertInstanceOf(KeyEnv::class, $client);
    }

    public function testConstructorRequiresToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new KeyEnv('');
    }

    public function testSecretFromArray(): void
    {
        $data = [
            'id' => 'sec_123',
            'environment_id' => 'env_456',
            'key' => 'DATABASE_URL',
            'type' => 'string',
            'version' => 1,
            'description' => 'Database connection URL',
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-02T00:00:00Z',
        ];

        $secret = Secret::fromArray($data);

        $this->assertEquals('sec_123', $secret->id);
        $this->assertEquals('env_456', $secret->environmentId);
        $this->assertEquals('DATABASE_URL', $secret->key);
        $this->assertEquals('string', $secret->type);
        $this->assertEquals(1, $secret->version);
        $this->assertEquals('Database connection URL', $secret->description);
    }

    public function testSecretWithValueFromArray(): void
    {
        $data = [
            'id' => 'sec_123',
            'environment_id' => 'env_456',
            'key' => 'DATABASE_URL',
            'type' => 'string',
            'version' => 1,
            'value' => 'postgres://localhost/mydb',
            'inherited_from' => 'development',
        ];

        $secret = SecretWithValue::fromArray($data);

        $this->assertEquals('sec_123', $secret->id);
        $this->assertEquals('DATABASE_URL', $secret->key);
        $this->assertEquals('postgres://localhost/mydb', $secret->value);
        $this->assertEquals('development', $secret->inheritedFrom);
    }

    public function testEnvironmentFromArray(): void
    {
        $data = [
            'id' => 'env_123',
            'project_id' => 'proj_456',
            'name' => 'production',
            'inherits_from' => 'staging',
            'created_at' => '2024-01-01T00:00:00Z',
        ];

        $environment = Environment::fromArray($data);

        $this->assertEquals('env_123', $environment->id);
        $this->assertEquals('proj_456', $environment->projectId);
        $this->assertEquals('production', $environment->name);
        $this->assertEquals('staging', $environment->inheritsFrom);
    }

    public function testKeyEnvExceptionMethods(): void
    {
        $exception = new KeyEnvException(
            'Not found',
            404,
            'NOT_FOUND',
            ['resource' => 'secret']
        );

        $this->assertEquals('Not found', $exception->getMessage());
        $this->assertEquals(404, $exception->getStatusCode());
        $this->assertEquals('NOT_FOUND', $exception->getErrorCode());
        $this->assertEquals(['resource' => 'secret'], $exception->getDetails());
        $this->assertTrue($exception->isNotFound());
        $this->assertFalse($exception->isUnauthorized());
        $this->assertFalse($exception->isTimeout());
    }

    public function testKeyEnvExceptionUnauthorized(): void
    {
        $exception = new KeyEnvException('Unauthorized', 401);

        $this->assertTrue($exception->isUnauthorized());
        $this->assertFalse($exception->isNotFound());
    }

    public function testKeyEnvExceptionTimeout(): void
    {
        $exception = new KeyEnvException('Request timeout', 408);

        $this->assertTrue($exception->isTimeout());
        $this->assertFalse($exception->isNotFound());
    }

    public function testSecretToArray(): void
    {
        $data = [
            'id' => 'sec_123',
            'environment_id' => 'env_456',
            'key' => 'API_KEY',
            'type' => 'string',
            'version' => 2,
            'description' => 'API key',
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-02T00:00:00Z',
        ];

        $secret = Secret::fromArray($data);
        $array = $secret->toArray();

        $this->assertEquals('sec_123', $array['id']);
        $this->assertEquals('env_456', $array['environment_id']);
        $this->assertEquals('API_KEY', $array['key']);
    }

    public function testSecretWithValueToArray(): void
    {
        $data = [
            'id' => 'sec_123',
            'environment_id' => 'env_456',
            'key' => 'SECRET_KEY',
            'type' => 'string',
            'version' => 1,
            'value' => 'secret-value-123',
            'inherited_from' => null,
        ];

        $secret = SecretWithValue::fromArray($data);
        $array = $secret->toArray();

        $this->assertEquals('secret-value-123', $array['value']);
        $this->assertNull($array['inherited_from']);
    }

    public function testEnvironmentToArray(): void
    {
        $data = [
            'id' => 'env_123',
            'project_id' => 'proj_456',
            'name' => 'staging',
            'inherits_from' => null,
            'created_at' => '2024-01-01T00:00:00Z',
        ];

        $environment = Environment::fromArray($data);
        $array = $environment->toArray();

        $this->assertEquals('env_123', $array['id']);
        $this->assertEquals('proj_456', $array['project_id']);
        $this->assertEquals('staging', $array['name']);
    }

    // ==================== New Type Tests ====================

    public function testSecretHistoryFromArray(): void
    {
        $data = [
            'id' => 'hist_123',
            'secret_id' => 'sec_456',
            'value' => 'old-value',
            'version' => 2,
            'changed_by' => 'user_789',
            'changed_at' => '2024-01-15T12:00:00Z',
        ];

        $history = SecretHistory::fromArray($data);

        $this->assertEquals('hist_123', $history->id);
        $this->assertEquals('sec_456', $history->secretId);
        $this->assertEquals('old-value', $history->value);
        $this->assertEquals(2, $history->version);
        $this->assertEquals('user_789', $history->changedBy);
        $this->assertEquals('2024-01-15T12:00:00Z', $history->changedAt);
    }

    public function testSecretHistoryToArray(): void
    {
        $data = [
            'id' => 'hist_123',
            'secret_id' => 'sec_456',
            'value' => 'old-value',
            'version' => 2,
            'changed_by' => 'user_789',
            'changed_at' => '2024-01-15T12:00:00Z',
        ];

        $history = SecretHistory::fromArray($data);
        $array = $history->toArray();

        $this->assertEquals('hist_123', $array['id']);
        $this->assertEquals('sec_456', $array['secret_id']);
        $this->assertEquals('old-value', $array['value']);
        $this->assertEquals(2, $array['version']);
    }

    public function testBulkImportResultFromArray(): void
    {
        $data = [
            'created' => 3,
            'updated' => 2,
            'skipped' => 1,
        ];

        $result = BulkImportResult::fromArray($data);

        $this->assertEquals(3, $result->created);
        $this->assertEquals(2, $result->updated);
        $this->assertEquals(1, $result->skipped);
    }

    public function testBulkImportResultToArray(): void
    {
        $data = [
            'created' => 5,
            'updated' => 0,
            'skipped' => 2,
        ];

        $result = BulkImportResult::fromArray($data);
        $array = $result->toArray();

        $this->assertEquals(5, $array['created']);
        $this->assertEquals(0, $array['updated']);
        $this->assertEquals(2, $array['skipped']);
    }

    public function testBulkImportResultDefaultValues(): void
    {
        $result = BulkImportResult::fromArray([]);

        $this->assertEquals(0, $result->created);
        $this->assertEquals(0, $result->updated);
        $this->assertEquals(0, $result->skipped);
    }

    public function testEnvironmentPermissionFromArray(): void
    {
        $data = [
            'id' => 'perm_123',
            'environment_id' => 'env_456',
            'user_id' => 'user_789',
            'role' => 'write',
            'user_email' => 'dev@example.com',
            'user_name' => 'Dev User',
            'granted_by' => 'admin_001',
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-02T00:00:00Z',
        ];

        $perm = EnvironmentPermission::fromArray($data);

        $this->assertEquals('perm_123', $perm->id);
        $this->assertEquals('env_456', $perm->environmentId);
        $this->assertEquals('user_789', $perm->userId);
        $this->assertEquals('write', $perm->role);
        $this->assertEquals('dev@example.com', $perm->userEmail);
        $this->assertEquals('Dev User', $perm->userName);
        $this->assertEquals('admin_001', $perm->grantedBy);
    }

    public function testEnvironmentPermissionToArray(): void
    {
        $data = [
            'id' => 'perm_123',
            'environment_id' => 'env_456',
            'user_id' => 'user_789',
            'role' => 'admin',
            'user_email' => 'admin@example.com',
            'user_name' => 'Admin User',
            'granted_by' => null,
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => null,
        ];

        $perm = EnvironmentPermission::fromArray($data);
        $array = $perm->toArray();

        $this->assertEquals('perm_123', $array['id']);
        $this->assertEquals('user_789', $array['user_id']);
        $this->assertEquals('admin', $array['role']);
        $this->assertEquals('admin@example.com', $array['user_email']);
    }

    public function testEnvironmentPermissionOptionalFields(): void
    {
        $data = [
            'id' => 'perm_123',
            'environment_id' => 'env_456',
            'user_id' => 'user_789',
            'role' => 'read',
        ];

        $perm = EnvironmentPermission::fromArray($data);

        $this->assertNull($perm->userEmail);
        $this->assertNull($perm->userName);
        $this->assertNull($perm->grantedBy);
        $this->assertNull($perm->createdAt);
        $this->assertNull($perm->updatedAt);
    }

    public function testProjectDefaultFromArray(): void
    {
        $data = [
            'id' => 'def_123',
            'project_id' => 'proj_456',
            'environment_name' => 'production',
            'default_role' => 'read',
            'created_at' => '2024-01-01T00:00:00Z',
        ];

        $default = ProjectDefault::fromArray($data);

        $this->assertEquals('def_123', $default->id);
        $this->assertEquals('proj_456', $default->projectId);
        $this->assertEquals('production', $default->environmentName);
        $this->assertEquals('read', $default->defaultRole);
        $this->assertEquals('2024-01-01T00:00:00Z', $default->createdAt);
    }

    public function testProjectDefaultToArray(): void
    {
        $data = [
            'id' => 'def_123',
            'project_id' => 'proj_456',
            'environment_name' => 'development',
            'default_role' => 'write',
            'created_at' => '2024-01-01T00:00:00Z',
        ];

        $default = ProjectDefault::fromArray($data);
        $array = $default->toArray();

        $this->assertEquals('def_123', $array['id']);
        $this->assertEquals('proj_456', $array['project_id']);
        $this->assertEquals('development', $array['environment_name']);
        $this->assertEquals('write', $array['default_role']);
    }

    // ==================== Deprecated Method Alias Tests ====================

    public function testGetSecretsMethodExists(): void
    {
        $this->assertTrue(
            method_exists(KeyEnv::class, 'getSecrets'),
            'Deprecated getSecrets() method should still exist as alias'
        );
    }

    public function testGetSecretsAsArrayMethodExists(): void
    {
        $this->assertTrue(
            method_exists(KeyEnv::class, 'getSecretsAsArray'),
            'Deprecated getSecretsAsArray() method should still exist as alias'
        );
    }

    public function testExportSecretsMethodExists(): void
    {
        $this->assertTrue(
            method_exists(KeyEnv::class, 'exportSecrets'),
            'New exportSecrets() method should exist'
        );
    }

    public function testExportSecretsAsArrayMethodExists(): void
    {
        $this->assertTrue(
            method_exists(KeyEnv::class, 'exportSecretsAsArray'),
            'New exportSecretsAsArray() method should exist'
        );
    }

    // ==================== New Method Existence Tests ====================

    public function testNewMethodsExist(): void
    {
        $expectedMethods = [
            'getProject',
            'createProject',
            'deleteProject',
            'createEnvironment',
            'deleteEnvironment',
            'getSecretHistory',
            'bulkImport',
            'listPermissions',
            'setPermission',
            'deletePermission',
            'bulkSetPermissions',
            'getMyPermissions',
            'getProjectDefaults',
            'setProjectDefaults',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                method_exists(KeyEnv::class, $method),
                "Method {$method}() should exist on KeyEnv class"
            );
        }
    }
}
