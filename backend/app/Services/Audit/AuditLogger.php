<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Exceptions\AuditLoggingUnavailable;
use App\Models\AuditActivity;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Spatie\Activitylog\ActivityLogger;

class AuditLogger
{
    public function __construct(
        private readonly AuditSanitizer $sanitizer,
        private readonly AuditContextResolver $contextResolver,
        private readonly Request $request,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function withoutModelEvents(Closure $callback): mixed
    {
        return $this->activityLogger->withoutLogs($callback);
    }

    public function assertAvailable(): void
    {
        if (! config('activitylog.enabled', true)) {
            throw new AuditLoggingUnavailable('Synchronous audit logging is disabled.');
        }

        $defaultConnection = config('database.default');
        $auditConnection = config('activitylog.database_connection');
        if (is_string($auditConnection) && $auditConnection !== '' && $auditConnection !== $defaultConnection) {
            throw new AuditLoggingUnavailable('Synchronous audit logging must use the application database connection.');
        }
    }

    public function recordMutation(
        AuditAction $action,
        AuditDomain $domain,
        Model $subject,
        array $changedFields,
        array $details = [],
        ?Model $context = null,
    ): ?AuditActivity {
        $changedFields = collect($changedFields)
            ->filter(fn (mixed $field): bool => is_string($field) && preg_match('/^[a-z0-9_.:-]+$/iD', $field) === 1)
            ->reject(fn (string $field): bool => in_array($field, ['id', 'uuid', 'created_at', 'updated_at', 'rnd_user_id', 'user_id', 'created_by'], true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($action === AuditAction::Updated && $changedFields === []) {
            return null;
        }

        $identifier = $subject->getAttribute('uuid') ?? $subject->getKey();
        $resolvedContext = $this->contextResolver->resolve($subject, $context);

        return $this->record(
            $action,
            AuditCategory::Operations,
            $domain,
            subject: $subject,
            context: $context,
            details: [
                ...$details,
                'public_id' => is_string($identifier) ? $identifier : null,
                'record_id' => is_int($identifier) ? $identifier : null,
                'context_public_id' => $resolvedContext?->getAttribute('uuid'),
                'changed_fields' => $changedFields,
            ],
        );
    }

    public function record(
        AuditAction $action,
        AuditCategory $category,
        AuditDomain $domain,
        ?Model $subject = null,
        ?Model $context = null,
        AuditOutcome $outcome = AuditOutcome::Success,
        AuditSeverity $severity = AuditSeverity::Info,
        array $details = [],
        ?Authenticatable $actor = null,
        ?string $systemActor = null,
        bool $includeRequestMetadata = true,
    ): AuditActivity {
        return $this->recordEvent(
            $action->value,
            $action,
            $category,
            $domain,
            $subject,
            $context,
            $outcome,
            $severity,
            $details,
            $actor,
            $systemActor,
            $includeRequestMetadata,
        );
    }

    private function recordEvent(
        string $event,
        AuditAction $action,
        AuditCategory $category,
        AuditDomain $domain,
        ?Model $subject = null,
        ?Model $context = null,
        AuditOutcome $outcome = AuditOutcome::Success,
        AuditSeverity $severity = AuditSeverity::Info,
        array $details = [],
        ?Authenticatable $actor = null,
        ?string $systemActor = null,
        bool $includeRequestMetadata = true,
    ): AuditActivity {
        $this->assertAvailable();

        if ($actor !== null && $systemActor !== null) {
            throw new InvalidArgumentException('An audit event cannot have both a user actor and a system actor.');
        }

        if ($systemActor !== null) {
            $systemActor = trim($this->sanitizer->text($systemActor) ?? '');

            if ($systemActor === '') {
                throw new InvalidArgumentException('The system actor must not be blank.');
            }
        }

        $resolvedActor = $actor ?? ($systemActor === null ? Auth::user() : null);
        if ($resolvedActor !== null && ! $resolvedActor instanceof Model) {
            throw new InvalidArgumentException('The audit actor must be an Eloquent model.');
        }

        $resolvedContext = $this->contextResolver->resolve($subject, $context);
        $clinicalIdentifiers = ['root_patient_id' => null, 'ncp_record_id' => null];
        $clinicalOwnerId = null;
        if ($category === AuditCategory::Clinical && $subject !== null) {
            $subjectIdentifiers = $this->contextResolver->clinicalIdentifiers($subject);
            $contextIdentifiers = $context !== null
                ? $this->contextResolver->clinicalIdentifiers($context)
                : ['root_patient_id' => null, 'ncp_record_id' => null];
            $clinicalIdentifiers = [
                'root_patient_id' => $subjectIdentifiers['root_patient_id'] ?? $contextIdentifiers['root_patient_id'],
                'ncp_record_id' => $subjectIdentifiers['ncp_record_id'] ?? $contextIdentifiers['ncp_record_id'],
            ];
            $details = [
                ...$clinicalIdentifiers,
                ...array_diff_key($details, $clinicalIdentifiers),
            ];
            $clinicalOwnerId = $this->contextResolver->clinicalOwnerId($subject)
                ?? ($context !== null ? $this->contextResolver->clinicalOwnerId($context) : null);
        }
        $safeDetails = $this->sanitizer->details($details, $category);
        $requestProperties = $includeRequestMetadata ? $this->sanitizer->request($this->request) : [];
        $properties = [
            'actor' => $this->sanitizer->actor($resolvedActor, $systemActor),
            'details' => $safeDetails,
            ...array_diff_key($safeDetails, array_flip(['actor', 'details', 'request'])),
            ...($includeRequestMetadata ? [
                'request' => $requestProperties,
                'ip' => $requestProperties['ip'],
                'user_agent' => $requestProperties['user_agent'],
            ] : []),
        ];

        $logger = activity(config('audit.log_name'))
            ->event($event)
            ->withProperties($properties)
            ->tap(function (AuditActivity $activity) use ($category, $domain, $outcome, $severity, $resolvedContext, $clinicalIdentifiers, $clinicalOwnerId): void {
                $activity->category = $category;
                $activity->domain = $domain;
                $activity->outcome = $outcome;
                $activity->severity = $severity;
                $activity->root_patient_id = $clinicalIdentifiers['root_patient_id'];
                $activity->ncp_record_id = $clinicalIdentifiers['ncp_record_id'];
                $activity->audit_owner_id = $clinicalOwnerId;

                if ($resolvedContext !== null) {
                    $activity->context_type = $resolvedContext->getMorphClass();
                    $activity->context_id = $resolvedContext->getKey();
                }
            });

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if ($resolvedActor !== null) {
            $logger->causedBy($resolvedActor);
        } else {
            $logger->causedByAnonymous();
        }

        $activity = $logger->log($action->label());
        if (! $activity instanceof AuditActivity) {
            throw new AuditLoggingUnavailable('The audit activity could not be persisted.');
        }

        return $activity;
    }
}
