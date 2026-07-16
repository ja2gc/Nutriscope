<?php

namespace App\Services\Audit;

class LegacyAuditEntityLabels
{
    private const LABELS = [
        'Inventory' => ['inventory', 'Inventory record'],
    ];

    /** @return array{0: string, 1: string}|null */
    public function for(?string $storedType): ?array
    {
        if (! is_string($storedType) || trim($storedType) === '') {
            return null;
        }

        foreach (self::LABELS as $class => $label) {
            if ($storedType === $class
                || $storedType === 'App\\Models\\'.$class
                || strtolower($storedType) === strtolower($class)) {
                return $label;
            }
        }

        return null;
    }
}
