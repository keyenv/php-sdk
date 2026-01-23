<?php

declare(strict_types=1);

namespace KeyEnv\Types;

/**
 * Represents an environment in a project.
 */
class Environment
{
    public function __construct(
        public readonly string $id,
        public readonly string $projectId,
        public readonly string $name,
        public readonly ?string $inheritsFrom = null,
        public readonly ?string $createdAt = null,
    ) {
    }

    /**
     * Create an Environment from an API response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            projectId: (string) $data['project_id'],
            name: (string) $data['name'],
            inheritsFrom: $data['inherits_from'] ?? null,
            createdAt: $data['created_at'] ?? null,
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
            'project_id' => $this->projectId,
            'name' => $this->name,
            'inherits_from' => $this->inheritsFrom,
            'created_at' => $this->createdAt,
        ];
    }
}
