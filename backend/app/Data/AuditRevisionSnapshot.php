<?php

namespace App\Data;

final readonly class AuditRevisionSnapshot
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $serializer,
        public string $subjectType,
        public string $subjectPublicId,
        public int $schemaVersion,
        public array $payload,
    ) {}
}
