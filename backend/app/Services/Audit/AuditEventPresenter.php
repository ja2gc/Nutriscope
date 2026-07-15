<?php

namespace App\Services\Audit;

use App\Data\AuditEventDto;
use App\Data\AuditHistoryLinkDto;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\AuditActivity;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;

class AuditEventPresenter
{
    public function __construct(
        private readonly AuditValuePresenter $values,
        private readonly AuditEntityPresenter $entities,
        private readonly AuditEventSummaryFormatter $summaries,
        private readonly AuditRevisionRegistry $revisions,
    ) {}

    public function present(
        AuditActivity $activity,
        ?User $viewer = null,
        ?Model $currentRecord = null,
    ): AuditEventDto {
        $category = $this->enumValue($activity->category, AuditCategory::Operations);
        $domain = $this->enumValue($activity->domain, AuditDomain::System);
        $module = $activity->module instanceof AuditModule
            ? $activity->module->value
            : 'legacy_unclassified';
        $severity = $this->enumValue($activity->severity, AuditSeverity::Info);
        $outcome = $this->enumValue($activity->outcome, AuditOutcome::Success);
        [$action, $actionLabel] = $this->action((string) $activity->event);
        $properties = $activity->properties?->all() ?? [];
        $storedDetails = is_array($properties['details'] ?? null) ? $properties['details'] : [];
        $clinical = $category === AuditCategory::Clinical->value;
        $actor = $this->actor($activity, $properties);
        $subject = $this->entities->present(
            $activity->subject_type,
            $this->publicId($activity, 'subject'),
            $clinical,
            $action,
        );
        $context = $activity->context_type === null
            ? null
            : $this->entities->present(
                $activity->context_type,
                $this->publicId($activity, 'context'),
                $clinical,
                $action,
            );
        $details = $this->values->details($storedDetails, $category, $domain);
        $changes = $this->values->changes($storedDetails, $properties, $category, $domain);
        $authenticatedUser = Auth::user();
        $viewer ??= $authenticatedUser instanceof User ? $authenticatedUser : null;
        $history = $this->history($activity, $action, $viewer);
        $summary = $this->summaries->format(
            $action,
            $actionLabel,
            $actor,
            $subject,
            $context,
            $changes,
        );
        if ($clinical && $changes !== [] && $action === AuditAction::Updated->value) {
            $summary = rtrim($summary, '.').'; values hidden.';
        }

        return new AuditEventDto(
            id: $this->uuid($activity->public_id)
                ?? Uuid::uuid5(Uuid::NAMESPACE_OID, (string) config('app.key').'|audit|'.$activity->getKey())->toString(),
            module: $module,
            category: $category,
            domain: $domain,
            recordType: $subject['type'],
            action: $action,
            actionLabel: $actionLabel,
            summary: $summary,
            severity: $severity,
            outcome: $outcome,
            actor: $actor,
            subject: $subject,
            context: $context,
            patient: $this->patient($activity, $clinical),
            ncpReference: $this->ncpReference($storedDetails, $clinical),
            detailMode: $history !== null ? 'history' : ($clinical ? 'field_names' : 'changes'),
            reason: $clinical ? null : $this->safeText($storedDetails['reason'] ?? null),
            history: $history,
            currentRecordUrl: $this->entities->currentRecordUrl(
                $this->relatedCurrentRecord($activity, $currentRecord),
                $viewer,
            ),
            occurredAt: $activity->created_at?->toISOString() ?? '',
            details: $details,
            changes: $changes,
        );
    }

    /** @return array{0: string, 1: string} */
    private function action(string $storedEvent): array
    {
        if (trim($storedEvent) === '') {
            return [AuditAction::Updated->value, AuditAction::Updated->label()];
        }

        $canonical = config('audit.legacy.action_aliases.'.$storedEvent, $storedEvent);
        $candidate = is_string($canonical) ? $canonical : $storedEvent;
        $candidate = strtolower(trim($candidate));
        if (preg_match('/^[a-z0-9_.:-]{1,64}$/D', $candidate) !== 1) {
            $candidate = 'legacy_event';
        }

        $known = AuditAction::tryFrom($candidate);

        return [$candidate, $known?->label() ?? ucfirst(str_replace(['_', '.', ':', '-'], ' ', $candidate))];
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

    /** @return array{display_name: string}|null */
    private function patient(AuditActivity $activity, bool $clinical): ?array
    {
        if (! $clinical) {
            return null;
        }

        $name = $this->safeText($activity->patient_display_name_snapshot);

        return $name === null ? null : ['display_name' => $name];
    }

    private function ncpReference(array $details, bool $clinical): ?string
    {
        $reference = $details['ncp_reference'] ?? null;

        return $clinical && is_string($reference)
            && preg_match('/^NCP-[A-F0-9]{16}$/D', $reference) === 1
                ? $reference
                : null;
    }

    private function history(AuditActivity $activity, string $action, ?User $viewer): ?AuditHistoryLinkDto
    {
        if ($viewer?->role !== 'Admin'
            || ! $activity->relationLoaded('revision')
            || $activity->revision === null
            || ! $this->revisions->supports($activity->revision)) {
            return null;
        }

        $revisionId = $this->uuid($activity->revision->public_id);
        $eventId = $this->uuid($activity->public_id);
        if ($revisionId === null || $eventId === null) {
            return null;
        }

        $label = match ($action) {
            AuditAction::Created->value => 'View created version',
            AuditAction::Deleted->value => 'View deleted version',
            AuditAction::Archived->value => 'View archived version',
            default => 'View audited changes',
        };

        return new AuditHistoryLinkDto(
            id: $revisionId,
            action: $action,
            label: $label,
            url: "/api/admin/audit-logs/{$eventId}/history",
        );
    }

    private function relatedCurrentRecord(AuditActivity $activity, ?Model $record): ?Model
    {
        if ($record === null || $record->getKey() === null) {
            return null;
        }

        $key = (int) $record->getKey();
        $directlyRelated = ($activity->subject_type === $record->getMorphClass()
                && (int) $activity->subject_id === $key)
            || ($activity->context_type === $record->getMorphClass()
                && (int) $activity->context_id === $key);
        $rootRelated = ($record instanceof Patient && (int) $activity->root_patient_id === $key)
            || ($record instanceof NcpRecord && (int) $activity->ncp_record_id === $key);

        return $directlyRelated || $rootRelated ? $record : null;
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
}
