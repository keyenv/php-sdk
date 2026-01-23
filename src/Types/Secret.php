<?php

declare(strict_types=1);

namespace KeyEnv\Types;

/**
 * Represents a secret without its decrypted value.
 */
class Secret
{
    public function __construct(
        public readonly string $id,
        public readonly string $environmentId,
        public readonly string $key,
        public readonly string $type,
        public readonly int $version,
        public readonly ?string $description = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * Create a Secret from an API response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            environmentId: (string) $data['environment_id'],
            key: (string) $data['key'],
            type: (string) ($data['type'] ?? 'string'),
            version: (int) $data['version'],
            description: $data['description'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'environment_id' => $this->environmentId,
            'key' => $this->key,
            'type' => $this->type,
            'version' => $this->version,
            'description' => $this->description,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
