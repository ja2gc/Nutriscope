<?php

namespace App\Models\Concerns;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\AuditActivity;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Services\Audit\AuditContextResolver;
use App\Services\Audit\AuditEventPolicy;
use App\Services\Audit\AuditPatientSnapshot;
use App\Services\Audit\AuditPseudonymousReference;
use App\Services\Audit\AuditPublicIdResolver;
use App\Services\Audit\AuditSanitizer;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Shared audit instrumentation (Spec 5). Logs the model's explicit audit allow-list,
 * only-dirty, no empty logs, under the 'audit' log name; causer is the auth user.
 *
 * Clinical models set `protected bool $auditRedactValues = true;` — we then strip
 * the before/after VALUES (keeping the changed field NAMES) so PHI never lands in
 * activity_log (Decision A). Operational models log full values.
 */
trait AuditsChanges
{
    use LogsActivity {
        shouldLogEvent as protected shouldLogActivityEvent;
    }

    /** @return array<int, string> */
    abstract protected function auditAttributes(): array;

    protected function shouldLogEvent(string $eventName): bool
    {
        if (config('audit.seeding.suppress_model_events', false)) {
            return false;
        }

        return $this->shouldLogActivityEvent($eventName);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->auditAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('audit');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($activity instanceof AuditActivity && $activity->module !== null) {
            return;
        }

        $resolver = app(AuditContextResolver::class);
        $policy = app(AuditEventPolicy::class)->forEvent(
            AuditAction::from($eventName),
            $this,
            ($this->auditRedactValues ?? false) ? AuditCategory::Clinical : AuditCategory::Operations,
            $resolver->domain($this),
        );
        $clinical = $policy['category'] === AuditCategory::Clinical;
        $sanitizer = app(AuditSanitizer::class);
        $props = $activity->properties;
        $changedFields = collect(['attributes', 'old'])
            ->flatMap(fn (string $bag) => array_keys((array) ($props[$bag] ?? [])))
            ->unique()
            ->values()
            ->all();

        if (! $clinical) {
            $props = collect($sanitizer->details(
                $activity->properties->all(),
                AuditCategory::Operations,
            ));
        } else {
            $props->forget(['attributes', 'old']);
        }

        $causer = $activity->causer;
        $props['actor'] ??= $sanitizer->actor($causer instanceof Model ? $causer : null);
        $props['request'] ??= $sanitizer->request(request());
        $activity->properties = $props;
        $publicIds = app(AuditPublicIdResolver::class);
        $activity->subject_public_id = $publicIds->forModel($this);
        $context = $resolver->resolve($this);
        if ($clinical) {
            $identifiers = $resolver->clinicalIdentifiers($this);
            $ncpSubject = $this instanceof NcpRecord
                ? $this
                : ($context instanceof NcpRecord ? $context : null);
            $ncpReference = app(AuditPseudonymousReference::class)->resolve(
                $ncpSubject,
                $identifiers['ncp_record_id'],
            );
            $props['details'] = $sanitizer->details([
                'changed_fields' => $changedFields,
                ...($ncpReference !== null ? ['ncp_reference' => $ncpReference] : []),
            ], AuditCategory::Clinical);
            $activity->properties = $props;
            $activity->root_patient_id = $identifiers['root_patient_id'];
            $activity->ncp_record_id = $identifiers['ncp_record_id'];
            $activity->audit_owner_id = $resolver->clinicalOwnerId($this);
            $patientSubject = $this instanceof Patient
                ? $this
                : ($context instanceof Patient ? $context : null);
            $activity->patient_display_name_snapshot = app(AuditPatientSnapshot::class)->resolve(
                $patientSubject,
                $identifiers['root_patient_id'],
            );
        }
        $activity->category = $policy['category'];
        $activity->domain = $policy['domain'];
        $activity->module = $policy['module'];
        $activity->outcome ??= AuditOutcome::Success;
        $activity->severity ??= AuditSeverity::Info;

        if ($activity->context_type === null && $activity->context_id === null) {
            if ($context !== null) {
                $activity->context_type = $context->getMorphClass();
                $activity->context_id = $context->getKey();
                $activity->context_public_id = $publicIds->forModel($context);
            }
        }
    }
}
