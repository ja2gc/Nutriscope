<?php

namespace App\Services\Audit;

class AuditRouteTemplateFormatter
{
    public function format(mixed $value): ?string
    {
        if (! is_string($value)
            || $value === ''
            || mb_strlen($value) > 255
            || preg_match('/[\x00-\x1F\x7F-\x9F]/u', $value) === 1
            || str_starts_with($value, '//')) {
            return null;
        }

        $segment = '(?:[A-Za-z0-9._~-]+|\{[A-Za-z_][A-Za-z0-9_]*\??\})';

        return preg_match('/^\/?'.$segment.'(?:\/'.$segment.')*$/D', $value) === 1 ? $value : null;
    }
}
