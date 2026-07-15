<?php

namespace App\Data;

final readonly class AuditHistoryLinkDto
{
    public function __construct(
        public string $id,
        public string $action,
        public string $label,
        public string $url,
    ) {}

    /** @return array{id: string, action: string, label: string, url: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'label' => $this->label,
            'url' => $this->url,
        ];
    }
}
