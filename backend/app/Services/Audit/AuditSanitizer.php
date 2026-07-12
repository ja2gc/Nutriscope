<?php

namespace App\Services\Audit;

use App\Enums\AuditCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditSanitizer
{
    private const MAX_DETAIL_LENGTH = 1024;

    private const FORBIDDEN_KEY_PARTS = [
        'password',
        'token',
        'secret',
        'authorization',
        'cookie',
        'verificationcode',
        'snapshot',
        'prompt',
        'response',
        'ocr',
        'body',
        'payload',
        'credential',
        'otp',
        'passcode',
        'filecontent',
        'output',
        'prompt',
        'completion',
        'medicaldiagnosis',
        'clinicalvalue',
        'weight',
        'height',
        'bmi',
        'labvalue',
        'medication',
        'allergy',
        'symptom',
        'medicalhistory',
    ];

    private const CLINICAL_SCALAR_KEYS = [
        'route', 'route_name', 'method', 'status', 'status_code', 'document_type',
        'attachment_type', 'format', 'count', 'source', 'generation_type',
        'identifier', 'public_id', 'reason_code', 'record_id', 'root_patient_id',
        'ncp_record_id',
    ];

    public function details(array $details, AuditCategory $category): array
    {
        if ($category === AuditCategory::Clinical) {
            return $this->clinicalDetails($details);
        }

        return $this->sanitizeArray($details);
    }

    public function request(Request $request): array
    {
        $ip = $this->sanitizeString((string) $request->ip());

        return [
            'ip' => filter_var($ip, FILTER_VALIDATE_IP) === false ? null : $ip,
            'url' => $this->sanitizeUrl($request->fullUrl()),
            'user_agent' => mb_substr($this->sanitizeString((string) $request->userAgent()), 0, 512),
        ];
    }

    public function text(?string $value, int $maxLength = 255): ?string
    {
        return $value === null ? null : mb_substr($this->sanitizeString($value), 0, $maxLength);
    }

    public function actor(?Model $actor, ?string $systemActor = null): array
    {
        if ($actor !== null) {
            return [
                'public_id' => $this->text($actor->getAttribute('uuid'), 64),
                'name' => $this->text($actor->getAttribute('name')),
                'role' => $this->text($actor->getAttribute('role'), 64),
                'kind' => 'user',
            ];
        }

        return [
            'public_id' => null,
            'name' => $this->text($systemActor),
            'role' => null,
            'kind' => $systemActor === null ? 'anonymous' : 'system',
        ];
    }

    private function clinicalDetails(array $details): array
    {
        $sanitized = [];

        foreach ($details as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (in_array($key, ['changed_fields', 'fields'], true) && is_array($value)) {
                $sanitized[$key] = collect($value)
                    ->filter(fn (mixed $field): bool => is_string($field))
                    ->map(fn (string $field): string => $this->text($field, 128) ?? '')
                    ->filter(fn (string $field): bool => preg_match('/^[a-z0-9_.:-]+$/iD', $field) === 1)
                    ->unique()
                    ->take(100)
                    ->values()
                    ->all();

                continue;
            }

            if (in_array($key, self::CLINICAL_SCALAR_KEYS, true)
                && (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null)) {
                if (is_string($value) && in_array($key, ['identifier', 'public_id'], true)) {
                    if (filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false) {
                        $sanitized[$key] = $this->maskEmail($value);
                    } elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', trim($value)) === 1) {
                        $sanitized[$key] = strtolower(trim($value));
                    }

                    continue;
                }

                $sanitized[$key] = is_string($value) ? $this->sanitizeAcceptedString($value, $key) : $value;
            }
        }

        return $sanitized;
    }

    private function sanitizeArray(array $values, int $depth = 0): array
    {
        if ($depth >= 8) {
            return [];
        }

        $sanitized = [];
        $normalizedKeys = [];
        $collisions = [];

        foreach (array_slice($values, 0, 100, true) as $key => $value) {
            if (is_string($key)) {
                if (strlen($key) > 128
                    || preg_match('/^[\x20-\x7E]+$/D', $key) !== 1
                    || $this->isForbiddenKey($key)) {
                    continue;
                }

                $normalizedKey = $this->normalizeKey($key);
                if ($normalizedKey === '' || isset($collisions[$normalizedKey])) {
                    continue;
                }

                if (isset($normalizedKeys[$normalizedKey])) {
                    unset($sanitized[$normalizedKeys[$normalizedKey]], $normalizedKeys[$normalizedKey]);
                    $collisions[$normalizedKey] = true;

                    continue;
                }
            }

            if (! is_array($value) && ! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value) && $value !== null) {
                continue;
            }

            $sanitizedKey = is_string($key) ? $this->text($key, 128) : $key;
            if ($sanitizedKey === null || $sanitizedKey === '') {
                continue;
            }

            if (is_string($key)) {
                $normalizedKeys[$normalizedKey] = $sanitizedKey;
            }

            $sanitized[$sanitizedKey] = match (true) {
                is_array($value) => $this->sanitizeArray($value, $depth + 1),
                is_string($value) && is_string($key) && str_contains($this->normalizeKey($key), 'email') => $this->maskEmail($value),
                is_string($value) => $this->sanitizeAcceptedString($value, is_string($key) ? $key : null),
                default => $value,
            };
        }

        return $sanitized;
    }

    private function sanitizeString(string $value): string
    {
        $value = (string) preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $value);

        $url = parse_url($value);
        if (is_array($url) && isset($url['scheme'], $url['host']) && in_array(strtolower($url['scheme']), ['http', 'https'], true)) {
            $value = strtolower($url['scheme']).'://'.$url['host']
                .(isset($url['port']) ? ':'.$url['port'] : '')
                .($url['path'] ?? '');
        }

        return mb_substr($value, 0, self::MAX_DETAIL_LENGTH);
    }

    private function sanitizeUrl(string $value): string
    {
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $value));

        if (str_starts_with($value, '//')) {
            $url = parse_url('http:'.$value);

            $sanitized = is_array($url) && isset($url['host'])
                ? '//'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '').($url['path'] ?? '')
                : '[redacted-url]';

            return mb_substr($sanitized, 0, self::MAX_DETAIL_LENGTH);
        }

        $url = parse_url($value);
        if (is_array($url) && isset($url['scheme'])) {
            if (! in_array(strtolower($url['scheme']), ['http', 'https'], true) || ! isset($url['host'])) {
                return '[redacted-url]';
            }

            return mb_substr(strtolower($url['scheme']).'://'.$url['host']
                .(isset($url['port']) ? ':'.$url['port'] : '')
                .($url['path'] ?? ''), 0, self::MAX_DETAIL_LENGTH);
        }

        return mb_substr((string) preg_replace('/[?#].*$/', '', $value), 0, self::MAX_DETAIL_LENGTH);
    }

    private function isForbiddenKey(string $key): bool
    {
        $normalized = $this->normalizeKey($key);

        foreach (self::FORBIDDEN_KEY_PARTS as $forbidden) {
            if (str_contains($normalized, $forbidden)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeKey(string $key): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $key));
    }

    private function isUrlKey(string $key): bool
    {
        $key = $this->normalizeKey($key);

        return str_contains($key, 'url')
            || str_contains($key, 'uri')
            || str_contains($key, 'path')
            || str_contains($key, 'route');
    }

    private function sanitizeAcceptedString(string $value, ?string $key): string
    {
        if (filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false) {
            return $this->maskEmail($value);
        }

        if (($key !== null && $this->isUrlKey($key))
            || preg_match('/^(?:[a-z][a-z0-9+.-]*:)?\/\//i', trim($value)) === 1) {
            return $this->sanitizeUrl($value);
        }

        return $this->sanitizeString($value);
    }

    private function maskEmail(string $email): string
    {
        $email = strtolower(trim($this->sanitizeString($email)));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return mb_substr($email, 0, 1).str_repeat('*', min(8, max(3, mb_strlen($email) - 1)));
        }

        $dot = strrpos($domain, '.');
        $domainName = $dot === false ? $domain : substr($domain, 0, $dot);
        $suffix = $dot === false ? '' : substr($domain, $dot);

        return mb_substr($local, 0, 1).'***@'.mb_substr($domainName, 0, 1).'***'.$suffix;
    }
}
