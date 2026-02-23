<?php

declare(strict_types=1);

namespace KeyEnv\Types;

/**
 * Represents the result of a bulk import operation.
 */
class BulkImportResult
{
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
    ) {
    }

    /**
     * Create a BulkImportResult from an API response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            created: (int) ($data['created'] ?? 0),
            updated: (int) ($data['updated'] ?? 0),
            skipped: (int) ($data['skipped'] ?? 0),
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
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ];
    }
}
