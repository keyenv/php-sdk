<?php

declare(strict_types=1);

namespace KeyEnv;

/**
 * Exception thrown for KeyEnv API errors.
 */
class KeyEnvException extends \Exception
{
    private int $statusCode;
    private ?string $errorCode;
    /** @var array<string, mixed> */
    private array $details;

    /**
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param string|null $errorCode API error code
     * @param array<string, mixed> $details Additional error details
     */
    public function __construct(
        string $message,
        int $statusCode = 0,
        ?string $errorCode = null,
        array $details = []
    ) {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the API error code.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get additional error details.
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Check if this is a not found error.
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Check if this is an authentication error.
     */
    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    /**
     * Check if this is a timeout error.
     */
    public function isTimeout(): bool
    {
        return $this->statusCode === 408;
    }
}
