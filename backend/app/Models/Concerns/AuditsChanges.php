<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Shared audit instrumentation (Spec 5). Logs the model's fillable allow-list,
 * only-dirty, no empty logs, under the 'audit' log name; causer is the auth user.
 *
 * Clinical models set `protected bool $auditRedactValues = true;` — we then strip
 * the before/after VALUES (keeping the changed field NAMES) so PHI never lands in
 * activity_log (Decision A). Operational models log full values.
 */
trait AuditsChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('audit');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if (! ($this->auditRedactValues ?? false)) {
            return;
        }

        $props = $activity->properties;
        foreach (['attributes', 'old'] as $bag) {
            if (! isset($props[$bag]) || ! is_array($props[$bag])) {
                continue;
            }
            $props[$bag] = array_map(fn () => '••• redacted', $props[$bag]);
        }
        $activity->properties = $props;
    }
}
