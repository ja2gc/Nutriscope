<?php

namespace App\Data;

final readonly class BackupArchiveResult
{
    public function __construct(
        public string $objectKey,
        public int $bytes,
        public ?string $integrityValue,
        public bool $encrypted,
    ) {}

    public function withBytes(int $bytes): self
    {
        return new self($this->objectKey, $bytes, $this->integrityValue, $this->encrypted);
    }

    public function withIntegrity(int $bytes, string $integrityValue): self
    {
        return new self($this->objectKey, $bytes, $integrityValue, $this->encrypted);
    }
}
