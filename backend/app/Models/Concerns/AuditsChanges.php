<?php

namespace App\Models\Concerns;

use App\Enums\AuditCategory;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Services\Audit\AuditContextResolver;
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
    use LogsActivity;

    /** @return array<int, string> */
    abstract protected function auditAttributes(): array;

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
        $clinical = $this->auditRedactValues ?? false;
        $sanitizer = app(AuditSanitizer::class);
        $props = $activity->properties;

        if (! $clinical) {
            $props = collect($sanitizer->details(
                $activity->properties->all(),
                AuditCategory::Operations,
            ));
        } else {
            foreach (['attributes', 'old'] as $bag) {
                if (! isset($props[$bag]) || ! is_array($props[$bag])) {
                    continue;
                }
                $props[$bag] = array_map(fn () => '••• redacted', $props[$bag]);
            }
        }

        $causer = $activity->causer;
        $props['actor'] ??= $sanitizer->actor($causer instanceof Model ? $causer : null);
        $props['request'] ??= $sanitizer->request(request());
        $activity->properties = $props;
        $events = request()->attributes->get('_audit_events', []);
        $events[] = ['source' => 'model', 'subject_table' => $this->getTable()];
        request()->attributes->set('_audit_events', $events);

        $resolver = app(AuditContextResolver::class);
        $activity->category ??= $clinical ? AuditCategory::Clinical : AuditCategory::Operations;
        $activity->domain ??= $resolver->domain($this);
        $activity->outcome ??= AuditOutcome::Success;
        $activity->severity ??= AuditSeverity::Info;

        if ($activity->context_type === null && $activity->context_id === null) {
            $context = $resolver->resolve($this);

            if ($context !== null) {
                $activity->context_type = $context->getMorphClass();
                $activity->context_id = $context->getKey();
            }
        }
    }
}
