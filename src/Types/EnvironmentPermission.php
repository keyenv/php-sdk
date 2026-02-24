<?php

declare(strict_types=1);

namespace KeyEnv\Types;

/**
 * Represents an environment permission for a user.
 */
class EnvironmentPermission
{
    public function __construct(
        public readonly string $id,
        public readonly string $environmentId,
        public readonly string $userId,
        public readonly string $role,
        public readonly ?string $userEmail = null,
        public readonly ?string $userName = null,
        public readonly ?string $grantedBy = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * Create an EnvironmentPermission from an API response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            environmentId: (string) $data['environment_id'],
            userId: (string) $data['user_id'],
            role: (string) $data['role'],
            userEmail: $data['user_email'] ?? null,
            userName: $data['user_name'] ?? null,
            grantedBy: $data['granted_by'] ?? null,
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
            'user_id' => $this->userId,
            'role' => $this->role,
            'user_email' => $this->userEmail,
            'user_name' => $this->userName,
            'granted_by' => $this->grantedBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
