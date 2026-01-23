<?php

declare(strict_types=1);

namespace KeyEnv\Tests;

use KeyEnv\KeyEnv;
use KeyEnv\KeyEnvException;
use KeyEnv\Types\Secret;
use KeyEnv\Types\SecretWithValue;
use KeyEnv\Types\Environment;
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
}
