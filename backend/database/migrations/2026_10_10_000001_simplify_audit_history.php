<?php

use App\Enums\AuditModule;
use App\Models\MealPlan;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Models\ShoppingList;
use App\Services\Audit\AuditRetentionService;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REMOVED_EVENTS = [
        'audit_log_viewed', 'authorization_denied', 'access_denied', 'account_blocked',
        'account_unblocked', 'exported',
    ];

    private const REPORT_MODULES = [
        'demographic_census' => AuditModule::NutritionCare->value,
        'ncp_summary' => AuditModule::NutritionCare->value,
        'adime_individual' => AuditModule::NutritionCare->value,
        'adime_aggregate' => AuditModule::NutritionCare->value,
        'ncp_census' => AuditModule::NutritionCare->value,
        'patient_menu_plan' => AuditModule::NutritionCare->value,
        'accomplishment_report' => AuditModule::FoodServiceOperations->value,
        'program_project_activity' => AuditModule::FoodServiceOperations->value,
        'procurement_pack' => AuditModule::FoodServiceOperations->value,
        'menu_calendar' => AuditModule::FoodServiceOperations->value,
    ];

    public function up(): void
    {
        $connection = DB::connection(config('activitylog.database_connection'));

        app(AuditRetentionService::class)->withAuthorizedMigration(
            $connection,
            fn () => $this->simplify($connection),
        );
    }

    public function down(): void
    {
        // Normalized and removed audit history cannot be reconstructed.
    }

    private function simplify(Connection $connection): void
    {
        $table = config('activitylog.table_name');
        $this->reclassifyReports($connection, $table);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('properties->actor->kind', 'system')
            ->delete();

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->whereIn('event', self::REMOVED_EVENTS)
            ->delete();

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('outcome', 'blocked')
            ->delete();

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'viewed')
            ->where(fn ($query) => $query->whereNull('domain')->orWhere('domain', '!=', 'reports'))
            ->delete();

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('outcome', 'failure')
            ->whereNotIn('event', ['login_failed', 'authentication_failed', 'failed', 'rate_limit_exceeded'])
            ->delete();

        foreach ([
            'login_failed' => ['failed', 'Login attempt'],
            'authentication_failed' => ['failed', 'Login attempt'],
            'login_succeeded' => ['created', 'Session'],
            'logout' => ['deleted', 'Session'],
            'password_changed' => ['updated', 'Password'],
            'password_reset' => ['updated', 'Password'],
            'profile_changed' => ['updated', 'Profile'],
            'appointment_scheduled' => ['created', 'Appointment scheduled'],
            'appointment_cancelled' => ['updated', 'Appointment cancelled'],
            'appointment_no_show' => ['updated', 'Appointment marked no-show'],
            'appointment_rescheduled' => ['updated', 'Appointment rescheduled'],
            'visit_started' => ['created', 'Visit started'],
            'visit_completed' => ['updated', 'Visit completed'],
            'visit_ended_early' => ['updated', 'Visit ended early'],
            'cycle_completed' => ['updated', 'NCP cycle completed'],
            'cycle_discontinued' => ['discontinued', 'NCP cycle discontinued'],
            'account_suspended' => ['suspended', 'User account'],
            'account_unsuspended' => ['unsuspended', 'User account'],
            'received' => ['updated', 'Goods received'],
            'reversed' => ['updated', 'Reversal'],
        ] as $event => [$action, $subjectLabel]) {
            $this->normalizeEventWithSubject($connection, $table, $event, $action, $subjectLabel);
        }

        $this->normalizeRateLimitEvents($connection, $table);

        $createdTypes = [(new MealPlan)->getMorphClass(), (new ShoppingList)->getMorphClass()];
        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'generated')
            ->whereIn('subject_type', $createdTypes)
            ->update(['event' => 'created', 'description' => 'Created']);
        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'generated')
            ->update(['event' => 'updated', 'description' => 'Updated']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'ordered')
            ->update(['event' => 'created', 'description' => 'Created']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'approved')
            ->update(['event' => 'created', 'description' => 'Created']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->whereIn('event', [
                'adjusted', 'completed', 'received', 'reversed', 'uploaded', 'imported',
                'price_corrected', 'profile_changed', 'settings_changed', 'password_changed',
                'password_reset', 'recovery_email_changed', 'recovery_email_verified',
                'appointment_scheduled', 'appointment_cancelled', 'appointment_no_show',
                'appointment_rescheduled', 'visit_started', 'visit_completed', 'visit_ended_early',
                'cycle_completed',
            ])
            ->update(['event' => 'updated', 'description' => 'Updated']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'cycle_discontinued')
            ->update(['event' => 'discontinued', 'description' => 'Discontinued']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'account_suspended')
            ->update(['event' => 'suspended', 'description' => 'Suspended']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'account_unsuspended')
            ->update(['event' => 'unsuspended', 'description' => 'Unsuspended']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->whereIn('event', ['login_failed', 'authentication_failed'])
            ->update(['event' => 'failed', 'description' => 'Failed']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'login_succeeded')
            ->update(['event' => 'created', 'description' => 'Created']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'logout')
            ->update(['event' => 'deleted', 'description' => 'Deleted']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('outcome', 'failure')
            ->where('event', 'failed')
            ->update(['outcome' => 'success']);

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->whereNotIn('event', [
                'created', 'updated', 'deleted', 'approved', 'viewed', 'downloaded', 'archived',
                'failed', 'suspended', 'unsuspended', 'discontinued',
            ])
            ->delete();

        $this->normalizeRevisionActions($connection);
    }

    private function reclassifyReports(Connection $connection, string $table): void
    {
        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where(fn ($query) => $query->where('domain', 'reports')->orWhere('module', 'reports'))
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($connection, $table): void {
                foreach ($rows as $row) {
                    $properties = is_array($row->properties)
                        ? $row->properties
                        : (json_decode((string) $row->properties, true) ?: []);
                    $reportType = $properties['report_type'] ?? data_get($properties, 'details.report_type');

                    if (! is_string($reportType) && $row->subject_type === (new Report)->getMorphClass()) {
                        $reportType = DB::table((new Report)->getTable())->where('id', $row->subject_id)->value('type');
                    }

                    $knownReportType = is_string($reportType) && isset(self::REPORT_MODULES[$reportType]);
                    $isReportConfiguration = in_array($row->subject_type, [
                        (new ReportBranding)->getMorphClass(),
                        (new ReportTemplate)->getMorphClass(),
                    ], true);
                    $isReportEvent = $row->domain === 'reports'
                        || $row->module === 'reports'
                        || $row->subject_type === (new Report)->getMorphClass()
                        || $knownReportType;
                    if (! $isReportEvent) {
                        continue;
                    }
                    if ($isReportConfiguration || ! $knownReportType) {
                        $connection->table($table)->where('id', $row->id)->delete();

                        continue;
                    }

                    $module = self::REPORT_MODULES[$reportType];
                    $updates = ['module' => $module, 'domain' => 'reports'];
                    if ($knownReportType) {
                        $properties['report_type'] = $reportType;
                        $details = is_array($properties['details'] ?? null) ? $properties['details'] : [];
                        $details['report_type'] = $reportType;
                        $details['subject_label'] = $this->reportTypeLabel($reportType);
                        $properties['details'] = $details;
                        $updates['properties'] = json_encode($properties, JSON_THROW_ON_ERROR);
                    }
                    $connection->table($table)->where('id', $row->id)->update([
                        ...$updates,
                    ]);
                    $connection->table('audit_revisions')->where('activity_id', $row->id)->update([
                        'module' => $module,
                        'domain' => 'reports',
                    ]);
                }
            });
    }

    private function normalizeRevisionActions(Connection $connection): void
    {
        $createdTypes = [(new MealPlan)->getMorphClass(), (new ShoppingList)->getMorphClass()];
        $connection->table('audit_revisions')
            ->where('action', 'generated')
            ->whereIn('subject_type', $createdTypes)
            ->update(['action' => 'created']);
        $connection->table('audit_revisions')
            ->where('action', 'generated')
            ->update(['action' => 'updated']);

        $connection->table('audit_revisions')->where('action', 'ordered')->update(['action' => 'created']);
        $connection->table('audit_revisions')->where('action', 'approved')->update(['action' => 'created']);
        $connection->table('audit_revisions')->whereIn('action', [
            'adjusted', 'completed', 'received', 'reversed', 'uploaded', 'imported', 'price_corrected',
            'profile_changed', 'settings_changed', 'password_changed', 'password_reset',
            'recovery_email_changed', 'recovery_email_verified', 'appointment_scheduled',
            'appointment_cancelled', 'appointment_no_show', 'appointment_rescheduled', 'visit_started',
            'visit_completed', 'visit_ended_early', 'cycle_completed',
        ])->update(['action' => 'updated']);
        $connection->table('audit_revisions')->where('action', 'cycle_discontinued')->update(['action' => 'discontinued']);
        $connection->table('audit_revisions')->where('action', 'account_suspended')->update(['action' => 'suspended']);
        $connection->table('audit_revisions')->where('action', 'account_unsuspended')->update(['action' => 'unsuspended']);
        $connection->table('audit_revisions')->whereIn('action', ['login_failed', 'authentication_failed'])->update(['action' => 'failed']);
        $connection->table('audit_revisions')->where('action', 'login_succeeded')->update(['action' => 'created']);
        $connection->table('audit_revisions')->where('action', 'logout')->update(['action' => 'deleted']);
    }

    private function reportTypeLabel(string $reportType): string
    {
        return match ($reportType) {
            'ncp_summary' => 'NCP Summary',
            'adime_individual' => 'Nutrition Intervention Plan',
            'adime_aggregate' => 'ADIME Aggregate',
            'ncp_census' => 'NCP Census',
            'program_project_activity' => 'PPA',
            default => str($reportType)->replace('_', ' ')->title()->toString(),
        };
    }

    private function normalizeEventWithSubject(
        Connection $connection,
        string $table,
        string $event,
        string $action,
        string $subjectLabel,
    ): void {
        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', $event)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($connection, $table, $action, $subjectLabel): void {
                foreach ($rows as $row) {
                    $properties = is_array($row->properties)
                        ? $row->properties
                        : (json_decode((string) $row->properties, true) ?: []);
                    $details = is_array($properties['details'] ?? null) ? $properties['details'] : [];
                    $details['subject_label'] = $subjectLabel;
                    $properties['details'] = $details;
                    $connection->table($table)->where('id', $row->id)->update([
                        'event' => $action,
                        'description' => ucfirst($action),
                        'outcome' => $action === 'failed' ? 'success' : $row->outcome,
                        'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
                    ]);
                }
            });
    }

    private function normalizeRateLimitEvents(Connection $connection, string $table): void
    {
        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'rate_limit_exceeded')
            ->where('domain', 'accounts')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($connection, $table): void {
                foreach ($rows as $row) {
                    $properties = is_array($row->properties)
                        ? $row->properties
                        : (json_decode((string) $row->properties, true) ?: []);
                    $details = is_array($properties['details'] ?? null) ? $properties['details'] : [];
                    $details['subject_label'] = 'Login attempt';
                    $details['reason'] = 'Rate limit reached';
                    $properties['details'] = $details;
                    $connection->table($table)->where('id', $row->id)->update([
                        'event' => 'failed',
                        'description' => 'Failed',
                        'outcome' => 'success',
                        'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
                    ]);
                }
            });

        $connection->table($table)
            ->where('log_name', config('audit.log_name'))
            ->where('event', 'rate_limit_exceeded')
            ->delete();
    }
};
