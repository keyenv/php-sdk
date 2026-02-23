<?php

declare(strict_types=1);

namespace KeyEnv\Types;

/**
 * Represents a secret version history entry.
 */
class SecretHistory
{
    public function __construct(
        public readonly string $id,
        public readonly string $secretId,
        public readonly string $value,
        public readonly int $version,
        public readonly ?string $changedBy = null,
        public readonly ?string $changedAt = null,
    ) {
    }

    /**
     * Create a SecretHistory from an API response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            secretId: (string) $data['secret_id'],
            value: (string) ($data['value'] ?? ''),
            version: (int) $data['version'],
            changedBy: $data['changed_by'] ?? null,
            changedAt: $data['changed_at'] ?? null,
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
            'secret_id' => $this->secretId,
            'value' => $this->value,
            'version' => $this->version,
            'changed_by' => $this->changedBy,
            'changed_at' => $this->changedAt,
        ];
    }
}
