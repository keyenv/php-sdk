<?php

declare(strict_types=1);

namespace KeyEnv\Tests;

use KeyEnv\KeyEnv;
use KeyEnv\KeyEnvException;
use KeyEnv\Types\Environment;
use KeyEnv\Types\Secret;
use KeyEnv\Types\SecretWithValue;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that run against the live KeyEnv test API.
 *
 * Environment variables:
 *   - KEYENV_API_URL: API base URL (e.g., http://localhost:8081/api/v1)
 *   - KEYENV_TOKEN: Service token for authentication
 *   - KEYENV_PROJECT: Project slug (default: sdk-test)
 *
 * @group integration
 */
final class IntegrationTest extends TestCase
{
    private static ?KeyEnv $client = null;
    private static string $project = 'sdk-test';
    private static string $environment = 'development';
    private static string $testPrefix = '';

    /** @var string[] Keys created during tests for cleanup */
    private static array $createdKeys = [];

    protected function setUp(): void
    {
        if (!getenv('KEYENV_API_URL')) {
            $this->markTestSkipped('KEYENV_API_URL not set - skipping integration tests');
        }

        if (self::$client === null) {
            $token = getenv('KEYENV_TOKEN') ?: 'env_test_integration_token_12345';
            self::$project = getenv('KEYENV_PROJECT') ?: 'sdk-test';
            self::$testPrefix = 'PHP_SDK_TEST_' . time() . '_';

            // Create client - it will read KEYENV_API_URL from environment
            self::$client = KeyEnv::create($token);
        }
    }

    protected function tearDown(): void
    {
        // Cleanup is done in tearDownAfterClass
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up all created secrets
        if (self::$client !== null) {
            foreach (self::$createdKeys as $key) {
                try {
                    self::$client->deleteSecret(self::$project, self::$environment, $key);
                } catch (\Exception $e) {
                    // Ignore cleanup errors - secret may already be deleted
                }
            }
        }

        self::$createdKeys = [];
    }

    /**
     * Helper to generate unique test key names.
     */
    private function uniqueKey(string $suffix = ''): string
    {
        $key = self::$testPrefix . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        if ($suffix) {
            $key .= '_' . $suffix;
        }
        self::$createdKeys[] = $key;
        return $key;
    }

    // ==================== Project Tests ====================

    /**
     * @test
     */
    public function listProjects_returns_array_of_projects(): void
    {
        $projects = self::$client->listProjects();

        $this->assertIsArray($projects);
        $this->assertNotEmpty($projects, 'Should have at least one project');

        // Verify we can find our test project
        $found = false;
        foreach ($projects as $project) {
            $this->assertIsArray($project);
            $this->assertArrayHasKey('slug', $project);
            if ($project['slug'] === self::$project) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Test project "' . self::$project . '" should exist');
    }

    // ==================== Environment Tests ====================

    /**
     * @test
     */
    public function listEnvironments_returns_environment_objects(): void
    {
        $environments = self::$client->listEnvironments(self::$project);

        $this->assertIsArray($environments);
        $this->assertNotEmpty($environments, 'Should have at least one environment');

        // Verify the returned items are Environment objects
        foreach ($environments as $env) {
            $this->assertInstanceOf(Environment::class, $env);
            $this->assertNotEmpty($env->id);
            $this->assertNotEmpty($env->name);
        }

        // Verify development environment exists
        $envNames = array_map(fn($e) => $e->name, $environments);
        $this->assertContains('development', $envNames, 'Development environment should exist');
    }

    // ==================== Secret Export Tests ====================

    /**
     * @test
     */
    public function getSecrets_returns_secrets_with_values(): void
    {
        $secrets = self::$client->getSecrets(self::$project, self::$environment);

        $this->assertIsArray($secrets);

        // Verify each item is a SecretWithValue object
        foreach ($secrets as $secret) {
            $this->assertInstanceOf(SecretWithValue::class, $secret);
            $this->assertNotEmpty($secret->key);
            // Value may be empty string but should be accessible
            $this->assertTrue(property_exists($secret, 'value'));
        }
    }

    /**
     * @test
     */
    public function getSecretsAsArray_returns_key_value_pairs(): void
    {
        $secrets = self::$client->getSecretsAsArray(self::$project, self::$environment);

        $this->assertIsArray($secrets);

        // Each entry should be key => value string pairs
        foreach ($secrets as $key => $value) {
            $this->assertIsString($key);
            $this->assertIsString($value);
        }
    }

    // ==================== Secret CRUD Tests ====================

    /**
     * @test
     */
    public function createSecret_creates_new_secret(): void
    {
        $key = $this->uniqueKey('CREATE');
        $value = 'test-value-' . time();
        $description = 'Test secret created by PHP SDK integration tests';

        $secret = self::$client->createSecret(
            self::$project,
            self::$environment,
            $key,
            $value,
            $description
        );

        $this->assertInstanceOf(Secret::class, $secret);
        $this->assertEquals($key, $secret->key);
        $this->assertNotEmpty($secret->id);
    }

    /**
     * @test
     */
    public function getSecret_retrieves_secret_with_value(): void
    {
        // First create a secret
        $key = $this->uniqueKey('GET');
        $value = 'get-test-value-' . time();

        self::$client->createSecret(
            self::$project,
            self::$environment,
            $key,
            $value
        );

        // Then retrieve it
        $secret = self::$client->getSecret(
            self::$project,
            self::$environment,
            $key
        );

        $this->assertInstanceOf(SecretWithValue::class, $secret);
        $this->assertEquals($key, $secret->key);
        $this->assertEquals($value, $secret->value);
    }

    /**
     * @test
     */
    public function updateSecret_updates_existing_secret(): void
    {
        // First create a secret
        $key = $this->uniqueKey('UPDATE');
        $originalValue = 'original-value-' . time();

        self::$client->createSecret(
            self::$project,
            self::$environment,
            $key,
            $originalValue
        );

        // Update it
        $newValue = 'updated-value-' . time();
        $newDescription = 'Updated description';

        $updated = self::$client->updateSecret(
            self::$project,
            self::$environment,
            $key,
            $newValue,
            $newDescription
        );

        $this->assertInstanceOf(Secret::class, $updated);
        $this->assertEquals($key, $updated->key);

        // Verify the update
        $retrieved = self::$client->getSecret(
            self::$project,
            self::$environment,
            $key
        );

        $this->assertEquals($newValue, $retrieved->value);
    }

    /**
     * @test
     */
    public function setSecret_creates_when_not_exists(): void
    {
        $key = $this->uniqueKey('SET_NEW');
        $value = 'set-new-value-' . time();

        $secret = self::$client->setSecret(
            self::$project,
            self::$environment,
            $key,
            $value
        );

        $this->assertInstanceOf(Secret::class, $secret);
        $this->assertEquals($key, $secret->key);

        // Verify it was created
        $retrieved = self::$client->getSecret(
            self::$project,
            self::$environment,
            $key
        );

        $this->assertEquals($value, $retrieved->value);
    }

    /**
     * @test
     */
    public function setSecret_updates_when_exists(): void
    {
        // First create a secret
        $key = $this->uniqueKey('SET_UPDATE');
        $originalValue = 'set-original-' . time();

        self::$client->createSecret(
            self::$project,
            self::$environment,
            $key,
            $originalValue
        );

        // Use setSecret to update
        $newValue = 'set-updated-' . time();
        $secret = self::$client->setSecret(
            self::$project,
            self::$environment,
            $key,
            $newValue
        );

        $this->assertInstanceOf(Secret::class, $secret);

        // Verify the update
        $retrieved = self::$client->getSecret(
            self::$project,
            self::$environment,
            $key
        );

        $this->assertEquals($newValue, $retrieved->value);
    }

    /**
     * @test
     */
    public function deleteSecret_removes_secret(): void
    {
        // First create a secret
        $key = $this->uniqueKey('DELETE');
        $value = 'delete-test-value-' . time();

        self::$client->createSecret(
            self::$project,
            self::$environment,
            $key,
            $value
        );

        // Delete it
        self::$client->deleteSecret(
            self::$project,
            self::$environment,
            $key
        );

        // Remove from cleanup list since we just deleted it
        self::$createdKeys = array_filter(self::$createdKeys, fn($k) => $k !== $key);

        // Verify deletion - should throw not found exception
        $this->expectException(KeyEnvException::class);
        self::$client->getSecret(
            self::$project,
            self::$environment,
            $key
        );
    }

    /**
     * @test
     */
    public function listSecrets_returns_secrets_without_values(): void
    {
        // First create a secret to ensure we have at least one
        $key = $this->uniqueKey('LIST');
        $value = 'list-test-value-' . time();

        self::$client->createSecret(
            self::$project,
            self::$environment,
            $key,
            $value
        );

        // List secrets
        $secrets = self::$client->listSecrets(self::$project, self::$environment);

        $this->assertIsArray($secrets);
        $this->assertNotEmpty($secrets);

        // Verify each item is a Secret object (not SecretWithValue)
        foreach ($secrets as $secret) {
            $this->assertInstanceOf(Secret::class, $secret);
            $this->assertNotEmpty($secret->key);
            // Secret class doesn't have value property
            $this->assertFalse(
                $secret instanceof SecretWithValue,
                'listSecrets should return Secret objects, not SecretWithValue'
            );
        }

        // Verify our test secret is in the list
        $keys = array_map(fn($s) => $s->key, $secrets);
        $this->assertContains($key, $keys);
    }

    // ==================== Utility Method Tests ====================

    /**
     * @test
     */
    public function generateEnvFile_returns_valid_env_format(): void
    {
        // Create a test secret
        $key = $this->uniqueKey('ENVFILE');
        $value = 'env-file-test-value';

        self::$client->createSecret(
            self::$project,
            self::$environment,
            $key,
            $value
        );

        // Generate env file content
        $envContent = self::$client->generateEnvFile(self::$project, self::$environment);

        $this->assertIsString($envContent);
        $this->assertStringContainsString('# Generated by KeyEnv', $envContent);
        $this->assertStringContainsString("# Environment: {self::$environment}", $envContent);
        $this->assertStringContainsString("{$key}={$value}", $envContent);
    }

    /**
     * @test
     */
    public function generateEnvFile_quotes_values_with_special_characters(): void
    {
        // Create a secret with spaces
        $key = $this->uniqueKey('ENVQUOTE');
        $value = 'value with spaces and "quotes"';

        self::$client->createSecret(
            self::$project,
            self::$environment,
            $key,
            $value
        );

        // Generate env file content
        $envContent = self::$client->generateEnvFile(self::$project, self::$environment);

        // Value should be quoted
        $this->assertStringContainsString($key . '="', $envContent);
    }

    // ==================== Error Handling Tests ====================

    /**
     * @test
     */
    public function getSecret_throws_not_found_for_missing_key(): void
    {
        $this->expectException(KeyEnvException::class);

        try {
            self::$client->getSecret(
                self::$project,
                self::$environment,
                'NON_EXISTENT_KEY_' . time()
            );
        } catch (KeyEnvException $e) {
            $this->assertEquals(404, $e->getStatusCode());
            $this->assertTrue($e->isNotFound());
            throw $e;
        }
    }

    /**
     * @test
     */
    public function createSecret_throws_conflict_for_duplicate_key(): void
    {
        $key = $this->uniqueKey('DUPLICATE');
        $value = 'first-value';

        // Create the secret
        self::$client->createSecret(
            self::$project,
            self::$environment,
            $key,
            $value
        );

        // Try to create again with same key - should fail
        $this->expectException(KeyEnvException::class);

        try {
            self::$client->createSecret(
                self::$project,
                self::$environment,
                $key,
                'second-value'
            );
        } catch (KeyEnvException $e) {
            // Could be 409 Conflict or 400 Bad Request depending on API
            $this->assertGreaterThanOrEqual(400, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * @test
     */
    public function operations_on_invalid_project_throw_error(): void
    {
        $this->expectException(KeyEnvException::class);

        self::$client->listEnvironments('non-existent-project-' . time());
    }

    /**
     * @test
     */
    public function operations_on_invalid_environment_throw_error(): void
    {
        $this->expectException(KeyEnvException::class);

        self::$client->getSecrets(self::$project, 'non-existent-environment-' . time());
    }
}
