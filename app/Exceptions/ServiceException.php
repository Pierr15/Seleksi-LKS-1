<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception untuk kegagalan bisnis pada service layer.
 * Membawa HTTP status code dan daftar error per-field untuk respons API.
 */
class ServiceException extends RuntimeException
{
    public int $statusCode;

    /**
     * @param array<string, string> $errors Error per-field (opsional)
     */
    public function __construct(
        string $message,
        int $statusCode = 400,
        private array $errors = [],
        private array $payload = [],
    ) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
