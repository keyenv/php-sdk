<?php

declare(strict_types=1);

namespace KeyEnv\Types;

/**
 * Represents a secret with its decrypted value.
 */
class SecretWithValue extends Secret
{
    public function __construct(
        string $id,
        string $environmentId,
        string $key,
        string $type,
        int $version,
        public readonly string $value,
        public readonly ?string $inheritedFrom = null,
        ?string $description = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
    ) {
        parent::__construct(
            $id,
            $environmentId,
            $key,
            $type,
            $version,
            $description,
            $createdAt,
            $updatedAt,
        );
    }

    /**
     * Create a SecretWithValue from an API response array.
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
            value: (string) ($data['value'] ?? ''),
            inheritedFrom: $data['inherited_from'] ?? null,
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
        return array_merge(parent::toArray(), [
            'value' => $this->value,
            'inherited_from' => $this->inheritedFrom,
        ]);
    }
}
