<?php

declare(strict_types=1);

namespace KeyEnv\Types;

/**
 * Represents a project default permission for an environment.
 */
class ProjectDefault
{
    public function __construct(
        public readonly string $id,
        public readonly string $projectId,
        public readonly string $environmentName,
        public readonly string $defaultRole,
        public readonly ?string $createdAt = null,
    ) {
    }

    /**
     * Create a ProjectDefault from an API response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            projectId: (string) $data['project_id'],
            environmentName: (string) $data['environment_name'],
            defaultRole: (string) $data['default_role'],
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
            'environment_name' => $this->environmentName,
            'default_role' => $this->defaultRole,
            'created_at' => $this->createdAt,
        ];
    }
}
