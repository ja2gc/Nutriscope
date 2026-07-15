<?php

namespace App\Services\Audit;

use App\Data\AuditValueDto;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;

class AuditValuePresenter
{
    private const DETAIL_FIELDS = [
        AuditCategory::Security->value => [
            'route_name' => 'text', 'method' => 'enum', 'status' => 'enum',
            'status_code' => 'number', 'limiter' => 'text', 'retry_after_seconds' => 'number',
            'previous_recurrence_count' => 'number', 'reason_code' => 'enum', 'format' => 'enum',
        ],
        AuditCategory::Clinical->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'enum',
            'status_code' => 'number', 'document_type' => 'enum', 'attachment_type' => 'enum',
            'format' => 'enum', 'count' => 'number', 'source' => 'enum',
            'generation_type' => 'enum', 'reason_code' => 'enum', 'report_type' => 'enum',
            'period_reference' => 'date',
        ],
    ];

    private const OPERATIONS_DETAIL_FIELDS = [
        AuditDomain::Accounts->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'enum',
        ],
        AuditDomain::Reports->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'enum',
            'format' => 'enum', 'report_type' => 'enum', 'count' => 'number',
        ],
        AuditDomain::Budget->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'enum',
            'count' => 'number', 'fiscal_year' => 'number',
        ],
        AuditDomain::Procurement->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'enum',
            'count' => 'number', 'source' => 'enum',
        ],
        AuditDomain::FoodService->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'enum',
            'count' => 'number', 'source' => 'enum',
        ],
        AuditDomain::NutritionLibrary->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'enum',
            'count' => 'number', 'source' => 'enum',
        ],
        AuditDomain::System->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'enum',
            'count' => 'number', 'format' => 'enum',
        ],
        AuditDomain::Patients->value => [],
        AuditDomain::Ncp->value => [],
    ];

    private const CHANGE_FIELDS = [
        AuditDomain::Accounts->value => [
            'name' => 'text', 'first_name' => 'text', 'last_name' => 'text',
            'role' => 'enum', 'is_active' => 'boolean',
        ],
        AuditDomain::Reports->value => [
            'status' => 'enum', 'type' => 'enum', 'format' => 'enum',
        ],
        AuditDomain::Budget->value => [
            'status' => 'enum', 'fiscal_year' => 'number', 'amount' => 'currency',
            'allocated_amount' => 'currency', 'used_amount' => 'currency',
            'remaining' => 'currency', 'per_head_day_limit' => 'currency',
        ],
        AuditDomain::Procurement->value => [
            'status' => 'enum', 'quantity' => 'number', 'unit_price' => 'currency',
            'total_amount' => 'currency', 'delivery_status' => 'enum',
            'estimated_population' => 'number', 'served_population' => 'number',
        ],
        AuditDomain::FoodService->value => [
            'name' => 'text', 'kind' => 'enum', 'base_unit' => 'enum',
            'status' => 'enum', 'quantity' => 'number', 'servings' => 'number',
            'meal_type' => 'enum', 'is_active' => 'boolean', 'purchase_price' => 'currency',
            'estimated_population' => 'number', 'served_population' => 'number',
        ],
        AuditDomain::NutritionLibrary->value => [
            'name' => 'text', 'category' => 'enum', 'serving_size' => 'number',
            'serving_unit' => 'enum', 'calories' => 'number', 'protein_g' => 'number',
            'carbs_g' => 'number', 'fat_g' => 'number', 'fiber_g' => 'number',
            'sodium_mg' => 'number', 'water_g' => 'number', 'servings' => 'number',
            'ready_to_eat' => 'boolean', 'is_active' => 'boolean',
        ],
        AuditDomain::System->value => [
            'status' => 'enum', 'is_active' => 'boolean', 'retention_enabled' => 'boolean',
        ],
        AuditDomain::Patients->value => [],
        AuditDomain::Ncp->value => [],
    ];

    public function __construct(
        private readonly AuditRouteTemplateFormatter $routeTemplateFormatter,
        private readonly AuditFieldLabels $fieldLabels,
    ) {}

    /** @return list<array{key: string, label: string, value: AuditValueDto}> */
    public function details(array $details, string $category, string $domain): array
    {
        $fields = $category === AuditCategory::Operations->value
            ? (self::OPERATIONS_DETAIL_FIELDS[$domain] ?? [])
            : (self::DETAIL_FIELDS[$category] ?? []);
        $presented = [];

        foreach ($fields as $key => $type) {
            if (! array_key_exists($key, $details)) {
                continue;
            }

            $value = $this->value($details[$key], $type, true, $key);
            if ($value === null) {
                continue;
            }

            $presented[] = [
                'key' => $key,
                'label' => $this->fieldLabels->label($key),
                'value' => $value,
            ];
        }

        return $presented;
    }

    /**
     * @return list<array{field: string, label: string, before: AuditValueDto, after: AuditValueDto, redacted: bool}>
     */
    public function changes(array $details, array $properties, string $category, string $domain): array
    {
        $fields = array_merge(
            is_array($details['changed_fields'] ?? null) ? $details['changed_fields'] : [],
            is_array($details['fields'] ?? null) ? $details['fields'] : [],
        );
        if ($category !== AuditCategory::Clinical->value) {
            $fields = array_merge(
                $fields,
                array_keys(is_array($properties['old'] ?? null) ? $properties['old'] : []),
                array_keys(is_array($properties['attributes'] ?? null) ? $properties['attributes'] : []),
            );
        }

        $fields = collect($fields)
            ->filter(fn (mixed $field): bool => is_string($field)
                && preg_match('/^[a-z0-9_.:-]+$/iD', $field) === 1)
            ->unique()->sort()->take(100)->values();

        if ($category === AuditCategory::Clinical->value) {
            return $fields->map(fn (string $field): array => [
                'field' => $field,
                'label' => $this->fieldLabels->label($field),
                'before' => new AuditValueDto('redacted', null),
                'after' => new AuditValueDto('redacted', null),
                'redacted' => true,
            ])->all();
        }

        $allowed = self::CHANGE_FIELDS[$domain] ?? [];
        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];
        $new = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];

        return $fields->filter(fn (string $field): bool => isset($allowed[$field]))
            ->map(function (string $field) use ($allowed, $old, $new): ?array {
                $before = $this->value($old[$field] ?? null, $allowed[$field], array_key_exists($field, $old), $field);
                $after = $this->value($new[$field] ?? null, $allowed[$field], array_key_exists($field, $new), $field);
                if ($before === null || $after === null) {
                    return null;
                }

                return [
                    'field' => $field,
                    'label' => $this->fieldLabels->label($field),
                    'before' => $before,
                    'after' => $after,
                    'redacted' => false,
                ];
            })->filter()->values()->all();
    }

    private function value(mixed $value, string $type, bool $present, string $key): ?AuditValueDto
    {
        if (! $present || $value === null) {
            return new AuditValueDto($type, null, currency: $type === 'currency' ? 'PHP' : null);
        }

        if ($type === 'field_list') {
            if (! is_array($value)) {
                return null;
            }

            $fields = collect($value)
                ->filter(fn (mixed $field): bool => is_string($field)
                    && preg_match('/^[a-z0-9_.:-]+$/iD', $field) === 1)
                ->unique()->sort()->take(100)->values()->all();

            return new AuditValueDto('field_list', $fields);
        }

        if (in_array($type, ['number', 'currency'], true)) {
            return is_int($value) || is_float($value)
                ? new AuditValueDto($type, $value, currency: $type === 'currency' ? 'PHP' : null)
                : null;
        }
        if ($type === 'boolean') {
            return is_bool($value) ? new AuditValueDto('boolean', $value) : null;
        }

        if ($key === 'route_name') {
            $route = $this->routeTemplateFormatter->format($value);

            return $route === null ? null : new AuditValueDto('text', $route);
        }
        if ($key === 'method') {
            return is_string($value) && in_array($value, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)
                ? new AuditValueDto('enum', $value)
                : null;
        }
        if ($type === 'text') {
            $text = $this->safeText($value);

            return $text === null ? null : new AuditValueDto('text', $text);
        }
        if ($type === 'date') {
            return is_string($value) && preg_match('/^\d{4}-\d{2}(?:-\d{2})?$/D', $value) === 1
                ? new AuditValueDto('date', $value)
                : null;
        }

        $token = $this->safeToken($value);

        return $token === null ? null : new AuditValueDto('enum', $token);
    }

    private function safeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $value));
        if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            || filter_var($value, FILTER_VALIDATE_IP) !== false
            || preg_match('/^(?:[a-z][a-z0-9+.-]*:)?\/\//i', $value) === 1) {
            return null;
        }

        return mb_substr($value, 0, 255);
    }

    private function safeToken(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value)
            && preg_match('/^[a-z0-9_.:\-]{1,64}$/D', $value) === 1
                ? $value
                : null;
    }
}
