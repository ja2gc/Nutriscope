<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Enums\BackupRetentionTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBackupSchedulesRequest;
use App\Models\BackupScheduleSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Backup\BackupReadiness;
use App\Services\Backup\DispatchDueBackups;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BackupScheduleController extends Controller
{
    public function __construct(
        private readonly BackupReadiness $readiness,
        private readonly DispatchDueBackups $dispatcher,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->data(BackupScheduleSetting::current())]);
    }

    public function update(UpdateBackupSchedulesRequest $request): JsonResponse
    {
        $settings = BackupScheduleSetting::current();
        $validated = $request->safe()->only(['daily', 'weekly', 'monthly']);
        if ($settings->anyEnabled() && ! in_array(true, $validated, true) && ! $request->boolean('confirm_disable_all')) {
            throw ValidationException::withMessages([
                'confirm_disable_all' => 'Confirm that all automatic backups will be disabled.',
            ]);
        }

        $enabling = collect($validated)->contains(
            fn (bool $enabled, string $category): bool => $enabled && ! $settings->{$category},
        );

        $readiness = $enabling ? $this->readiness->check() : null;
        if ($readiness !== null && ! $readiness['ready']) {
            return response()->json([
                'message' => 'Automatic backups cannot be enabled until backup readiness checks pass.',
                'readiness' => $readiness,
            ], 422);
        }

        DB::transaction(function () use ($settings, $validated): void {
            $old = $settings->only(['daily', 'weekly', 'monthly']);
            $settings->fill($validated);
            $changed = array_keys($settings->getDirty());
            $settings->save();

            $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::System,
                $settings,
                $changed,
                details: ['setting' => 'automatic_backup_schedules'],
                oldValues: $old,
                newValues: $settings->only(['daily', 'weekly', 'monthly']),
            );
        });

        return response()->json(['data' => $this->data($settings->refresh())]);
    }

    private function data(BackupScheduleSetting $settings): array
    {
        $now = now(config('nutriscope-backups.timezone'))->toImmutable();
        $data = [];

        foreach (BackupRetentionTier::cases() as $category) {
            $enabled = $settings->{$category->value};
            $data[$category->value] = [
                'enabled' => $enabled,
                'next_at' => $enabled ? $this->dispatcher->nextTarget($category, $now)->toIso8601String() : null,
            ];
        }

        return [
            ...$data,
            'message' => $settings->anyEnabled() ? null : 'Automatic backups are disabled.',
        ];
    }
}
