<?php

namespace Pdfember\Exceptions;

/**
 * The API answered with an error: `{"error": {"code", "message", "fields"?}}`.
 * Subclasses cover the statuses worth catching on their own.
 */
class ApiException extends PdfemberException
{
    /**
     * @param  array<string, array<int, string>>  $fields  validation messages per option (422 only)
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $errorCode,
        public readonly array $fields = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    /**
     * @param  array<string, array<int, string>>  $fields
     */
    public static function fromResponse(int $status, string $errorCode, string $message, array $fields = [], ?int $retryAfter = null): self
    {
        $class = match ($status) {
            401 => AuthenticationException::class,
            402 => QuotaExceededException::class,
            422 => ValidationException::class,
            429 => RateLimitException::class,
            default => self::class,
        };

        return new $class($message, $status, $errorCode, $fields, $retryAfter);
    }
}
