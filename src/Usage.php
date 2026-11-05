<?php

namespace Pdfember;

/** The plan and this month's document count, from GET /usage. */
final class Usage
{
    public function __construct(
        public readonly string $plan,
        public readonly int $documentsUsed,
        public readonly int $documentsIncluded,
        public readonly int $documentsLeft,
        public readonly string $periodStart,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['plan'] ?? ''),
            (int) ($data['documents_used'] ?? 0),
            (int) ($data['documents_included'] ?? 0),
            (int) ($data['documents_left'] ?? 0),
            (string) ($data['period_start'] ?? ''),
        );
    }
}
