<?php

namespace App\DTO;

class ParsedDocument
{
    public function __construct(
        public string $text,
        public float $confidence = 1.0,
        public int $processingTimeMs = 0
    ) {}
}
