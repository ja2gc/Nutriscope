<?php

namespace Tests\Feature\Audit;

use App\Models\AuditActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditHistoryCleanupMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_audit_rows_are_deleted_and_edit_actions_are_normalized(): void
    {
        $report = $this->activity('created', 'reports', 'reports', properties: ['report_type' => 'ncp_summary']);
        $reportViewed = $this->activity('viewed', 'reports', 'reports', properties: ['report_type' => 'demographic_census']);
        $reportConfiguration = $this->activity(
            'updated',
            'reports',
            'reports',
            subjectType: 'App\\Models\\ReportTemplate',
            properties: ['report_type' => 'procurement_pack'],
        );
        $blocked = $this->activity('account_blocked', 'security_administration', 'accounts');
        $denied = $this->activity('authorization_denied', 'security_administration', 'accounts');
        $blockedOutcome = $this->activity('updated', 'security_administration', 'accounts', 'blocked');
        $viewed = $this->activity('viewed', 'nutrition_care', 'ncp');
        $downloaded = $this->activity('downloaded', 'nutrition_care', 'ncp');
        $adjusted = $this->activity('adjusted', 'food_service_operations', 'budget');
        $failed = $this->activity('authentication_failed', 'security_administration', 'accounts', 'failure');
        $created = $this->activity('created', 'nutrition_care', 'patients');
        $password = $this->activity('password_changed', 'security_administration', 'accounts');
        $systemActor = $this->activity(
            'updated',
            'food_service_operations',
            'system',
            properties: ['actor' => ['kind' => 'system']],
        );
        $userActor = $this->activity(
            'updated',
            'food_service_operations',
            'food_service',
            properties: ['actor' => ['kind' => 'user']],
        );
        $generated = $this->activity(
            'generated',
            'nutrition_care',
            'ncp',
            subjectType: 'App\\Models\\MealPlan',
        );
        $unknown = $this->activity('unrecognized_action', 'security_administration', 'accounts');
        $nonAudit = $this->activity('viewed', 'nutrition_care', 'patients', 'success', 'default');

        foreach ([$report, $reportViewed, $reportConfiguration, $blocked, $denied, $blockedOutcome, $viewed, $downloaded, $adjusted, $failed, $created, $password, $systemActor, $userActor, $generated, $unknown, $nonAudit] as $activity) {
            DB::table('audit_revisions')->insert([
                'public_id' => (string) Str::uuid(),
                'activity_id' => $activity->id,
                'module' => $activity->module,
                'domain' => $activity->domain,
                'subject_type' => $activity->subjectType ?? 'App\\Models\\Patient',
                'subject_public_id' => (string) Str::uuid(),
                'action' => $activity->event,
                'schema_version' => 1,
                'occurred_at' => now(),
                'created_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_10_10_000001_simplify_audit_history.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('activity_log', [
            'id' => $report->id,
            'module' => 'nutrition_care',
            'domain' => 'reports',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'id' => $reportViewed->id,
            'event' => 'viewed',
            'module' => 'nutrition_care',
        ]);
        $this->assertDatabaseHas('audit_revisions', [
            'activity_id' => $report->id,
            'action' => 'created',
            'module' => 'nutrition_care',
        ]);
        foreach ([$reportConfiguration, $blocked, $denied, $blockedOutcome, $viewed, $unknown] as $removed) {
            $this->assertDatabaseMissing('activity_log', ['id' => $removed->id]);
            $this->assertDatabaseMissing('audit_revisions', ['activity_id' => $removed->id]);
        }
        $this->assertDatabaseMissing('activity_log', ['id' => $systemActor->id]);
        $this->assertDatabaseMissing('audit_revisions', ['activity_id' => $systemActor->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $userActor->id, 'event' => 'updated']);
        $this->assertDatabaseHas('audit_revisions', ['activity_id' => $userActor->id, 'action' => 'updated']);
        $this->assertDatabaseHas('activity_log', ['id' => $failed->id, 'event' => 'failed', 'outcome' => 'success']);
        $this->assertDatabaseHas('activity_log', ['id' => $downloaded->id, 'event' => 'downloaded']);
        $this->assertDatabaseHas('activity_log', ['id' => $generated->id, 'event' => 'created', 'description' => 'Created']);
        $this->assertDatabaseHas('activity_log', ['id' => $nonAudit->id, 'event' => 'viewed']);
        $this->assertDatabaseHas('audit_revisions', ['activity_id' => $created->id, 'action' => 'created']);
        $this->assertDatabaseHas('activity_log', [
            'id' => $adjusted->id,
            'event' => 'updated',
            'description' => 'Updated',
        ]);
        $this->assertDatabaseHas('audit_revisions', ['activity_id' => $adjusted->id, 'action' => 'updated']);
        $this->assertDatabaseHas('activity_log', ['id' => $password->id, 'event' => 'updated']);
        $this->assertDatabaseHas('audit_revisions', ['activity_id' => $password->id, 'action' => 'updated']);
        $this->assertDatabaseHas('audit_revisions', ['activity_id' => $generated->id, 'action' => 'created']);
    }

    private function activity(
        string $event,
        string $module,
        string $domain,
        string $outcome = 'success',
        string $logName = 'audit',
        ?string $subjectType = null,
        array $properties = [],
    ): object {
        $id = DB::table((new AuditActivity)->getTable())->insertGetId([
            'public_id' => (string) Str::uuid(),
            'log_name' => $logName,
            'description' => str($event)->headline()->toString(),
            'event' => $event,
            'category' => 'operations',
            'domain' => $domain,
            'module' => $module,
            'outcome' => $outcome,
            'subject_type' => $subjectType,
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) [
            'id' => $id,
            'module' => $module,
            'domain' => $domain,
            'event' => $event,
            'subjectType' => $subjectType,
        ];
    }
}
