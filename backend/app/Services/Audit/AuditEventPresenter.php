<?php

namespace App\Services\Audit;

use App\Data\AuditEventDto;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\AuditActivity;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class AuditEventPresenter
{
    private const ENTITY_LABELS = [
        'Announcement' => ['announcement', 'Announcement'],
        'Assessment' => ['assessment', 'Assessment'],
        'Budget' => ['budget', 'Budget'],
        'BudgetLedger' => ['budget_ledger', 'Budget ledger'],
        'Diagnosis' => ['diagnosis', 'Diagnosis'],
        'FoodItem' => ['food_item', 'Food item'],
        'FoodServiceRecipe' => ['food_service_recipe', 'Food service recipe'],
        'FsItem' => ['fs_item', 'Food service item'],
        'Intervention' => ['intervention', 'Intervention'],
        'Inventory' => ['inventory', 'Inventory record'],
        'MealPlan' => ['meal_plan', 'Meal plan'],
        'MealPlanDay' => ['meal_plan_day', 'Meal plan day'],
        'MealPlanItem' => ['meal_plan_item', 'Meal plan item'],
        'MealPlanTemplate' => ['meal_plan_template', 'Meal plan template'],
        'MealPrepLog' => ['meal_prep_log', 'Meal preparation log'],
        'MenuCycle' => ['menu_cycle', 'Menu cycle'],
        'MenuCycleDay' => ['menu_cycle_day', 'Menu cycle day'],
        'MenuCycleTemplate' => ['menu_cycle_template', 'Menu cycle template'],
        'Monitoring' => ['monitoring', 'Monitoring record'],
        'NcpRecord' => ['ncp_record', 'NCP record'],
        'Notification' => ['notification', 'Notification'],
        'Patient' => ['patient', 'Patient'],
        'ProgramProjectActivity' => ['program_project_activity', 'Program/project activity'],
        'PurchaseOrder' => ['purchase_order', 'Purchase order'],
        'PurchaseOrderAttachment' => ['purchase_order_attachment', 'Purchase order attachment'],
        'PurchaseOrderItem' => ['purchase_order_item', 'Purchase order item'],
        'PurchaseOrderItemCorrection' => ['purchase_order_item_correction', 'Purchase order item correction'],
        'PurchaseOrderVendorGroup' => ['purchase_order_vendor_group', 'Purchase order vendor group'],
        'Recipe' => ['recipe', 'Recipe'],
        'Report' => ['report', 'Report'],
        'ReportTemplate' => ['report_template', 'Report template'],
        'ScreeningDocument' => ['screening_document', 'Screening document'],
        'ShoppingList' => ['shopping_list', 'Shopping list'],
        'ShoppingListItem' => ['shopping_list_item', 'Shopping list item'],
        'Supplier' => ['supplier', 'Supplier'],
        'User' => ['user', 'User account'],
    ];

    private const SECURITY_DETAIL_FIELDS = [
        'route_name' => 'text',
        'method' => 'text',
        'status' => 'status',
        'status_code' => 'status',
        'limiter' => 'text',
        'retry_after_seconds' => 'number',
        'previous_recurrence_count' => 'number',
        'reason_code' => 'text',
        'format' => 'text',
    ];

    private const CLINICAL_DETAIL_FIELDS = [
        'changed_fields' => 'field_list',
        'fields' => 'field_list',
        'status' => 'status',
        'status_code' => 'status',
        'document_type' => 'text',
        'attachment_type' => 'text',
        'format' => 'text',
        'count' => 'number',
        'source' => 'text',
        'generation_type' => 'text',
        'reason_code' => 'text',
        'report_type' => 'text',
        'period_reference' => 'date',
    ];

    private const OPERATIONS_DETAIL_FIELDS = [
        AuditDomain::Accounts->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'status',
        ],
        AuditDomain::Reports->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'status',
            'format' => 'text', 'report_type' => 'text', 'count' => 'number',
        ],
        AuditDomain::Budget->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'status',
            'count' => 'number',
        ],
        AuditDomain::Procurement->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'status',
            'count' => 'number', 'source' => 'text',
        ],
        AuditDomain::FoodService->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'status',
            'count' => 'number', 'source' => 'text',
        ],
        AuditDomain::System->value => [
            'changed_fields' => 'field_list', 'fields' => 'field_list', 'status' => 'status',
            'count' => 'number', 'format' => 'text',
        ],
        AuditDomain::Patients->value => [],
        AuditDomain::Ncp->value => [],
    ];

    private const OPERATIONS_CHANGE_FIELDS = [
        AuditDomain::Accounts->value => [
            'name' => 'person_name', 'first_name' => 'person_name', 'last_name' => 'person_name',
            'role' => 'token', 'is_active' => 'boolean',
        ],
        AuditDomain::Reports->value => ['status' => 'token', 'type' => 'token', 'format' => 'token'],
        AuditDomain::Budget->value => [
            'status' => 'token', 'fiscal_year' => 'number', 'amount' => 'number',
            'allocated_amount' => 'number', 'used_amount' => 'number',
        ],
        AuditDomain::Procurement->value => [
            'status' => 'token', 'quantity' => 'number', 'unit_price' => 'number',
            'total_amount' => 'number', 'delivery_status' => 'token',
        ],
        AuditDomain::FoodService->value => [
            'status' => 'token', 'quantity' => 'number', 'servings' => 'number',
            'meal_type' => 'token', 'is_active' => 'boolean',
        ],
        AuditDomain::System->value => [
            'status' => 'token', 'is_active' => 'boolean', 'retention_enabled' => 'boolean',
        ],
        AuditDomain::Patients->value => [],
        AuditDomain::Ncp->value => [],
    ];

    public function __construct(private readonly AuditRouteTemplateFormatter $routeTemplateFormatter) {}

    public function present(AuditActivity $activity): AuditEventDto
    {
        $category = $this->enumValue($activity->category, AuditCategory::Operations);
        $domain = $this->enumValue($activity->domain, AuditDomain::System);
        $severity = $this->enumValue($activity->severity, AuditSeverity::Info);
        $outcome = $this->enumValue($activity->outcome, AuditOutcome::Success);
        $storedEvent = (string) $activity->event;
        $canonicalEvent = config('audit.legacy.action_aliases.'.$storedEvent, $storedEvent);
        $action = AuditAction::tryFrom(is_string($canonicalEvent) ? $canonicalEvent : $storedEvent)
            ?? AuditAction::Updated;
        $subject = $this->entity($activity->subject_type, $this->publicId($activity, 'subject'));
        $context = $this->entity($activity->context_type, $this->publicId($activity, 'context'));
        $properties = $activity->properties?->all() ?? [];
        $details = is_array($properties['details'] ?? null) ? $properties['details'] : [];

        return new AuditEventDto(
            id: $this->uuid($activity->public_id)
                ?? Uuid::uuid5(Uuid::NAMESPACE_OID, (string) config('app.key').'|audit|'.$activity->getKey())->toString(),
            category: $category,
            domain: $domain,
            action: $action->value,
            actionLabel: $action->label(),
            summary: $this->summary($action, $subject, $context),
            severity: $severity,
            outcome: $outcome,
            actor: $this->actor($activity, $properties),
            subject: $subject,
            context: $context,
            occurredAt: $activity->created_at?->toISOString() ?? '',
            details: $this->details($details, $category, $domain),
            changes: $this->changes($details, $properties, $category, $domain),
        );
    }

    private function enumValue(mixed $value, BackedEnum $fallback): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : ($value ?: (string) $fallback->value);
    }

    /** @return array{id: ?string, kind: string, name: string, role: ?string}|null */
    private function actor(AuditActivity $activity, array $properties): ?array
    {
        $snapshot = is_array($properties['actor'] ?? null) ? $properties['actor'] : [];
        $kind = in_array($snapshot['kind'] ?? null, ['user', 'system', 'anonymous'], true)
            ? $snapshot['kind']
            : null;
        $publicId = $this->uuid($snapshot['public_id'] ?? null);
        $role = in_array($snapshot['role'] ?? null, ['Admin', 'RND', 'FSS'], true) ? $snapshot['role'] : null;
        $name = $this->safeText($snapshot['name'] ?? null);

        if ($kind === 'user' && $publicId !== null && $name !== null) {
            return ['id' => $publicId, 'kind' => 'user', 'name' => $name, 'role' => $role];
        }

        if ($activity->relationLoaded('causer') && $activity->causer instanceof User) {
            return [
                'id' => $activity->causer->uuid,
                'kind' => 'user',
                'name' => $this->safeText($activity->causer->display_name) ?? 'User',
                'role' => in_array($activity->causer->role, ['Admin', 'RND', 'FSS'], true)
                    ? $activity->causer->role
                    : null,
            ];
        }

        if (in_array($kind, ['system', 'anonymous'], true)) {
            return ['id' => null, 'kind' => $kind, 'name' => $name ?? ucfirst($kind), 'role' => null];
        }

        return null;
    }

    /** @return array{type: string, id: ?string, label: string}|null */
    private function entity(?string $class, ?string $publicId): ?array
    {
        if ($class === null || $class === '') {
            return null;
        }

        $entity = str_starts_with($class, 'App\\Models\\')
            ? (self::ENTITY_LABELS[class_basename($class)] ?? ['record', 'Record'])
            : ['record', 'Record'];

        return ['type' => $entity[0], 'id' => $publicId, 'label' => $entity[1]];
    }

    private function publicId(AuditActivity $activity, string $entity): ?string
    {
        if (($uuid = $this->uuid($activity->getAttribute($entity.'_public_id'))) !== null) {
            return $uuid;
        }

        $details = $activity->properties['details'] ?? [];
        if (! is_array($details)) {
            return null;
        }

        $keys = $entity === 'subject'
            ? ['subject_public_id', 'public_id', 'report_public_id']
            : ['context_public_id'];

        foreach ($keys as $key) {
            if (($uuid = $this->uuid($details[$key] ?? null)) !== null) {
                return $uuid;
            }
        }

        return null;
    }

    /** @return list<array{key: string, label: string, kind: string, value: mixed}> */
    private function details(array $details, string $category, string $domain): array
    {
        $presented = [];
        $fields = match ($category) {
            AuditCategory::Security->value => self::SECURITY_DETAIL_FIELDS,
            AuditCategory::Clinical->value => self::CLINICAL_DETAIL_FIELDS,
            default => self::OPERATIONS_DETAIL_FIELDS[$domain] ?? [],
        };

        foreach ($fields as $key => $kind) {
            if (! array_key_exists($key, $details)) {
                continue;
            }

            $value = $details[$key];
            if ($kind === 'field_list') {
                $value = collect(is_array($value) ? $value : [])
                    ->filter(fn (mixed $field): bool => is_string($field) && preg_match('/^[a-z0-9_.:-]+$/iD', $field) === 1)
                    ->unique()->sort()->take(100)->values()->all();
            } elseif ($kind === 'number') {
                if (! is_int($value) && ! is_float($value)) {
                    continue;
                }
            } else {
                $value = match ($key) {
                    'method' => $this->safeMethod($value),
                    'route_name' => $this->routeTemplateFormatter->format($value),
                    default => $this->safeToken($value, $kind === 'date'),
                };
                if ($value === null) {
                    continue;
                }
            }

            $presented[] = [
                'key' => $key,
                'label' => Str::of($key)->replace('_', ' ')->title()->toString(),
                'kind' => $kind,
                'value' => $value,
            ];
        }

        return $presented;
    }

    /** @return list<array{field: string, label: string, old_value: null, new_value: null, redacted: bool}> */
    private function changes(array $details, array $properties, string $category, string $domain): array
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
            ->filter(fn (mixed $field): bool => is_string($field) && preg_match('/^[a-z0-9_.:-]+$/iD', $field) === 1)
            ->unique()->sort()->take(100)->values();

        if ($category === AuditCategory::Clinical->value) {
            return $fields->map(fn (string $field): array => [
                'field' => $field,
                'label' => Str::of($field)->replace(['_', '.'], ' ')->title()->toString(),
                'old_value' => null,
                'new_value' => null,
                'redacted' => true,
            ])->all();
        }

        $allowed = self::OPERATIONS_CHANGE_FIELDS[$domain] ?? [];
        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];
        $new = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];

        return $fields->filter(fn (string $field): bool => isset($allowed[$field]))
            ->map(function (string $field) use ($allowed, $old, $new): ?array {
                [$oldIsSafe, $oldValue] = $this->safeChangeValue($old[$field] ?? null, $allowed[$field], array_key_exists($field, $old));
                [$newIsSafe, $newValue] = $this->safeChangeValue($new[$field] ?? null, $allowed[$field], array_key_exists($field, $new));
                if (! $oldIsSafe || ! $newIsSafe) {
                    return null;
                }

                return [
                    'field' => $field,
                    'label' => Str::of($field)->replace(['_', '.'], ' ')->title()->toString(),
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'redacted' => false,
                ];
            })->filter()->values()->all();
    }

    /** @param array{type: string, id: ?string, label: string}|null $subject */
    private function summary(AuditAction $action, ?array $subject, ?array $context): string
    {
        $target = $subject['label'] ?? $context['label'] ?? 'audit event';

        return $action->label().' '.Str::lower($target);
    }

    private function uuid(mixed $value): ?string
    {
        return is_string($value) && Uuid::isValid($value) ? strtolower($value) : null;
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

    private function safeToken(mixed $value, bool $date = false): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (! is_string($value) || $value !== strtolower($value)) {
            return null;
        }

        $pattern = $date ? '/^\d{4}-\d{2}(?:-\d{2})?$/D' : '/^[a-z0-9_.:\-]{1,64}$/D';

        return preg_match($pattern, $value) === 1 ? $value : null;
    }

    private function safeMethod(mixed $value): ?string
    {
        return is_string($value) && in_array($value, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)
            ? $value
            : null;
    }

    /** @return array{0: bool, 1: string|int|float|bool|null} */
    private function safeChangeValue(mixed $value, string $kind, bool $present): array
    {
        if (! $present || $value === null) {
            return [true, null];
        }

        return match ($kind) {
            'token' => is_string($value) && ($token = $this->safeToken($value)) !== null
                ? [true, $token]
                : [false, null],
            'person_name' => is_string($value) && ($name = $this->safeText($value)) !== null
                ? [true, $name]
                : [false, null],
            'number' => is_int($value) || is_float($value) ? [true, $value] : [false, null],
            'boolean' => is_bool($value) ? [true, $value] : [false, null],
            default => [false, null],
        };
    }
}
