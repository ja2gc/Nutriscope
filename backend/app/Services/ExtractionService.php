<?php

namespace App\Services;

use App\Models\ExtractionTemplate;

class ExtractionService
{
    public function extract(ExtractionTemplate $template, string $text): array
    {
        $fields = $template->field_mappings ?: [];
        $extracted = [];

        foreach ($fields as $fieldName => $pattern) {
            $value = null;
            // Build safe case-insensitive regex
            if (@preg_match('/' . $pattern . '/i', $text, $matches)) {
                if (isset($matches[1])) {
                    $val = trim($matches[1]);
                    $value = is_numeric($val) ? (float) $val : $val;
                }
            }
            $extracted[$fieldName] = $value;
        }

        return $extracted;
    }

    public function calculateConfidence(array $extracted): float
    {
        if (empty($extracted)) {
            return 0.0;
        }

        $totalFields = count($extracted);
        $matchedFields = count(array_filter($extracted, fn($val) => !is_null($val) && $val !== ''));

        return round($matchedFields / $totalFields, 2);
    }
}
