<?php

declare(strict_types=1);

namespace KeyEnv\Tests;

use KeyEnv\KeyEnv;
use KeyEnv\KeyEnvException;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that run against the live KeyEnv API.
 * Requires KEYENV_SERVICE_TOKEN and KEYENV_PROJECT_ID environment variables.
 *
 * @group integration
 */
final class IntegrationTest extends TestCase
{
    private static ?KeyEnv $client = null;
    private static ?string $projectId = null;
    private static string $environment = 'development';
    private static ?string $testSecretKey = null;

    public static function setUpBeforeClass(): void
    {
        $token = getenv('KEYENV_SERVICE_TOKEN') ?: null;
        self::$projectId = getenv('KEYENV_PROJECT_ID') ?: null;

        if (empty($token) || empty(self::$projectId)) {
            self::markTestSkipped('KEYENV_SERVICE_TOKEN and KEYENV_PROJECT_ID must be set');
        }

        self::$client = KeyEnv::create($token);
        self::$testSecretKey = 'TEST_INTEGRATION_' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test secret
        if (self::$client !== null && self::$testSecretKey !== null) {
            try {
                self::$client->deleteSecret(self::$projectId, self::$environment, self::$testSecretKey);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
    }

    /**
     * @test
     */
    public function listProjects_returns_projects(): void
    {
        $projects = self::$client->listProjects();

        $this->assertIsArray($projects);
        $this->assertNotEmpty($projects, 'Should have at least one project');
    }

    /**
     * @test
     */
    public function getProject_returns_project_details(): void
    {
        $project = self::$client->getProject(self::$projectId);

        $this->assertIsArray($project);
        $this->assertEquals(self::$projectId, $project['id']);
        $this->assertArrayHasKey('name', $project);
    }

    /**
     * @test
     */
    public function listEnvironments_returns_environments(): void
    {
        $environments = self::$client->listEnvironments(self::$projectId);

        $this->assertIsArray($environments);
        $this->assertNotEmpty($environments, 'Should have at least one environment');
    }

    /**
     * @test
     * @depends listEnvironments_returns_environments
     */
    public function setSecret_creates_new_secret(): void
    {
        $testValue = 'test-value-' . time();

        $secret = self::$client->setSecret(
            self::$projectId,
            self::$environment,
            self::$testSecretKey,
            $testValue
        );

        $this->assertIsArray($secret);
        $this->assertEquals(self::$testSecretKey, $secret['key']);
    }

    /**
     * @test
     * @depends setSecret_creates_new_secret
     */
    public function getSecret_retrieves_created_secret(): void
    {
        $secret = self::$client->getSecret(
            self::$projectId,
            self::$environment,
            self::$testSecretKey
        );

        $this->assertIsArray($secret);
        $this->assertEquals(self::$testSecretKey, $secret['key']);
        $this->assertArrayHasKey('value', $secret);
        $this->assertStringStartsWith('test-value-', $secret['value']);
    }

    /**
     * @test
     * @depends setSecret_creates_new_secret
     */
    public function getSecrets_returns_all_secrets(): void
    {
        $secrets = self::$client->getSecrets(self::$projectId, self::$environment);

        $this->assertIsArray($secrets);

        $found = array_filter($secrets, fn($s) => $s['key'] === self::$testSecretKey);
        $this->assertNotEmpty($found, 'Should contain our test secret');
    }

    /**
     * @test
     * @depends getSecret_retrieves_created_secret
     */
    public function setSecret_updates_existing_secret(): void
    {
        $updatedValue = 'updated-value-' . time();

        $secret = self::$client->setSecret(
            self::$projectId,
            self::$environment,
            self::$testSecretKey,
            $updatedValue
        );

        $this->assertIsArray($secret);

        // Verify update
        self::$client->clearCache(self::$projectId, self::$environment);
        $retrieved = self::$client->getSecret(
            self::$projectId,
            self::$environment,
            self::$testSecretKey
        );
        $this->assertEquals($updatedValue, $retrieved['value']);
    }

    /**
     * @test
     * @depends setSecret_updates_existing_secret
     */
    public function generateEnvFile_generates_valid_content(): void
    {
        $envContent = self::$client->generateEnvFile(self::$projectId, self::$environment);

        $this->assertIsString($envContent);
        $this->assertStringContainsString(self::$testSecretKey . '=', $envContent);
    }

    /**
     * @test
     * @depends generateEnvFile_generates_valid_content
     */
    public function deleteSecret_removes_secret(): void
    {
        self::$client->deleteSecret(
            self::$projectId,
            self::$environment,
            self::$testSecretKey
        );

        // Verify deletion
        self::$client->clearCache(self::$projectId, self::$environment);

        $this->expectException(KeyEnvException::class);
        self::$client->getSecret(
            self::$projectId,
            self::$environment,
            self::$testSecretKey
        );
    }
}
