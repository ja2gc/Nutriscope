<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Models\AuditActivity;
use App\Models\AuditRevision;
use App\Models\FoodItem;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class AuditRedesignMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_taxonomy_and_additive_schema_match_the_approved_contract(): void
    {
        $this->assertSame([
            'security_administration',
            'nutrition_care',
            'food_service_operations',
            'reports',
        ], array_column(AuditModule::cases(), 'value'));
        $this->assertSame('nutrition_library', AuditDomain::NutritionLibrary->value);
        $this->assertSame([
            262_144,
            262_144,
            524_288,
            1_048_576,
            262_144,
            524_288,
        ], array_values(AuditRevision::SNAPSHOT_BYTE_LIMITS));

        $this->assertTrue(Schema::hasColumns('activity_log', [
            'module', 'patient_display_name_snapshot',
        ]));
        $this->assertTrue(Schema::hasTable('audit_revisions'));
        $this->assertTrue(Schema::hasColumns('audit_revisions', [
            'id', 'public_id', 'activity_id', 'module', 'domain', 'subject_type',
            'subject_public_id', 'action', 'schema_version', 'before', 'after',
            'occurred_at', 'created_at',
        ]));

        $activityIndexes = collect(Schema::getIndexes('activity_log'));
        $this->assertContains(
            ['module', 'created_at', 'id'],
            $activityIndexes->pluck('columns')->all(),
        );
        $this->assertFalse($activityIndexes->contains(
            fn (array $index): bool => in_array('patient_display_name_snapshot', $index['columns'], true),
        ));

        $revisionIndexes = collect(Schema::getIndexes('audit_revisions'));
        $this->assertTrue($revisionIndexes->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['public_id'],
        ));
        $this->assertTrue($revisionIndexes->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['activity_id'],
        ));
        $this->assertTrue(collect(Schema::getForeignKeys('audit_revisions'))->contains(
            fn (array $foreign): bool => $foreign['columns'] === ['activity_id']
                && $foreign['foreign_table'] === 'activity_log'
                && strtolower((string) $foreign['on_delete']) === 'cascade',
        ));

        $originalConnection = config('activitylog.database_connection');
        try {
            config(['activitylog.database_connection' => 'audit_connection_contract']);
            $this->assertSame('audit_connection_contract', (new AuditRevision)->getConnectionName());
        } finally {
            config(['activitylog.database_connection' => $originalConnection]);
        }
    }

    public function test_backfill_is_deterministic_idempotent_and_preserves_ambiguous_or_existing_values(): void
    {
        $patient = Patient::factory()->create([
            'first_name' => 'Maria Clara',
            'last_name' => 'De la Cruz',
            'name' => 'LEGACY NAME MUST NOT WIN',
        ]);
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id]);

        $patientEvent = $this->legacyActivity([
            'category' => 'clinical',
            'domain' => 'patients',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'root_patient_id' => $patient->id,
        ]);
        $ncpEvent = $this->legacyActivity([
            'category' => 'clinical',
            'domain' => 'ncp',
            'subject_type' => NcpRecord::class,
            'subject_id' => $ncp->id,
            'ncp_record_id' => $ncp->id,
        ]);
        $foodEvent = $this->legacyActivity([
            'category' => 'operations',
            'domain' => 'nutrition_library',
            'subject_type' => FoodItem::class,
            'subject_id' => 123,
        ]);
        $recipeEvent = $this->legacyActivity([
            'category' => 'operations',
            'domain' => 'food_service',
            'subject_type' => Recipe::class,
            'subject_id' => 456,
        ]);
        $reportEvent = $this->legacyActivity([
            'category' => 'operations',
            'domain' => 'reports',
            'subject_type' => Report::class,
            'subject_id' => 789,
        ]);
        $accountEvent = $this->legacyActivity([
            'category' => 'security',
            'domain' => 'accounts',
            'subject_type' => User::class,
            'subject_id' => 99,
        ]);
        $operationsEvent = $this->legacyActivity([
            'category' => 'operations',
            'domain' => 'procurement',
            'subject_type' => PurchaseOrder::class,
            'subject_id' => 98,
        ]);
        $ambiguousEvent = $this->legacyActivity([
            'category' => null,
            'domain' => null,
            'subject_type' => 'Legacy\\UnknownRecord',
        ]);
        $unresolvedPatientEvent = $this->legacyActivity([
            'category' => 'clinical',
            'domain' => 'ncp',
            'root_patient_id' => 999999,
            'properties' => ['details' => ['ncp_public_id' => 'NCP-PSEUDONYM']],
        ]);
        $preservedEvent = $this->legacyActivity([
            'category' => 'clinical',
            'domain' => 'patients',
            'root_patient_id' => $patient->id,
            'module' => AuditModule::Reports->value,
            'patient_display_name_snapshot' => 'Original Snapshot',
        ]);

        $migration = require database_path('migrations/2026_07_15_100003_backfill_audit_modules_and_patient_snapshots.php');
        $migration->up();
        $migration->up();

        $this->assertSame(AuditModule::NutritionCare, $patientEvent->fresh()->module);
        $this->assertSame(AuditModule::NutritionCare, $ncpEvent->fresh()->module);
        $this->assertSame('Maria Clara De la Cruz', $patientEvent->fresh()->patient_display_name_snapshot);
        $this->assertSame('Maria Clara De la Cruz', $ncpEvent->fresh()->patient_display_name_snapshot);
        $this->assertSame(AuditModule::NutritionCare, $foodEvent->fresh()->module);
        $this->assertSame(AuditDomain::NutritionLibrary, $foodEvent->fresh()->domain);
        $this->assertSame(AuditModule::NutritionCare, $recipeEvent->fresh()->module);
        $this->assertSame(AuditDomain::NutritionLibrary, $recipeEvent->fresh()->domain);
        $this->assertSame(AuditModule::Reports, $reportEvent->fresh()->module);
        $this->assertSame(AuditModule::SecurityAdministration, $accountEvent->fresh()->module);
        $this->assertSame(AuditModule::FoodServiceOperations, $operationsEvent->fresh()->module);
        $this->assertNull($ambiguousEvent->fresh()->module);
        $this->assertSame(AuditModule::NutritionCare, $unresolvedPatientEvent->fresh()->module);
        $this->assertNull($unresolvedPatientEvent->fresh()->patient_display_name_snapshot);
        $this->assertSame('NCP-PSEUDONYM', $unresolvedPatientEvent->fresh()->properties['details']['ncp_public_id']);
        $this->assertSame(AuditModule::Reports, $preservedEvent->fresh()->module);
        $this->assertSame('Original Snapshot', $preservedEvent->fresh()->patient_display_name_snapshot);

        $rawSnapshot = DB::table('activity_log')->where('id', $patientEvent->id)
            ->value('patient_display_name_snapshot');
        $this->assertNotSame('Maria Clara De la Cruz', $rawSnapshot);
        $this->assertStringNotContainsString('Maria Clara De la Cruz', (string) $patientEvent->fresh()->properties);
    }

    public function test_revision_model_is_bounded_allowlisted_immutable_and_pruned_with_parent(): void
    {
        $activity = $this->legacyActivity([
            'category' => 'operations',
            'domain' => 'nutrition_library',
            'module' => AuditModule::NutritionCare->value,
            'subject_type' => Recipe::class,
            'subject_public_id' => (string) Str::uuid(),
        ]);
        $revision = AuditRevision::create([
            'activity_id' => $activity->id,
            'module' => AuditModule::NutritionCare,
            'domain' => AuditDomain::NutritionLibrary,
            'subject_type' => Recipe::class,
            'subject_public_id' => $activity->subject_public_id,
            'action' => AuditAction::Updated,
            'schema_version' => 1,
            'before' => ['name' => 'Old recipe'],
            'after' => ['name' => 'New recipe'],
            'occurred_at' => now()->addDay(),
        ]);

        $this->assertTrue(Str::isUuid($revision->public_id));
        $this->assertSame(AuditModule::NutritionCare, $revision->module);
        $this->assertSame(AuditDomain::NutritionLibrary, $revision->domain);
        $this->assertSame(AuditAction::Updated, $revision->action);
        $this->assertSame('New recipe', $revision->after['name']);
        $this->assertTrue($revision->occurred_at->equalTo($activity->created_at));
        $this->assertTrue($activity->revision->is($revision));

        try {
            $revision->update(['schema_version' => 2]);
            $this->fail('Revision update unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit revisions are immutable.', $exception->getMessage());
        }

        $this->expectException(QueryException::class);
        AuditRevision::create([
            'activity_id' => $activity->id,
            'module' => AuditModule::NutritionCare,
            'domain' => AuditDomain::NutritionLibrary,
            'subject_type' => Recipe::class,
            'subject_public_id' => $activity->subject_public_id,
            'action' => AuditAction::Updated,
            'schema_version' => 1,
            'after' => ['name' => 'Duplicate'],
            'occurred_at' => now(),
        ]);
    }

    public function test_revision_rejects_clinical_types_oversized_payloads_and_direct_mutation(): void
    {
        $activity = $this->legacyActivity([
            'category' => 'operations',
            'domain' => 'procurement',
            'module' => AuditModule::FoodServiceOperations->value,
            'subject_type' => PurchaseOrder::class,
            'subject_public_id' => (string) Str::uuid(),
        ]);

        try {
            AuditRevision::create([
                'activity_id' => $activity->id,
                'module' => AuditModule::NutritionCare,
                'domain' => AuditDomain::Patients,
                'subject_type' => Patient::class,
                'subject_public_id' => $activity->subject_public_id,
                'action' => AuditAction::Updated,
                'schema_version' => 1,
                'after' => ['name' => 'PATIENT-SENTINEL'],
                'occurred_at' => now(),
            ]);
            $this->fail('Clinical revision unexpectedly succeeded.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Unsupported audit revision subject type.', $exception->getMessage());
        }

        try {
            AuditRevision::create([
                'activity_id' => $activity->id,
                'module' => AuditModule::FoodServiceOperations,
                'domain' => AuditDomain::Procurement,
                'subject_type' => PurchaseOrder::class,
                'subject_public_id' => $activity->subject_public_id,
                'action' => AuditAction::Updated,
                'schema_version' => 1,
                'after' => ['notes' => str_repeat('x', AuditRevision::SNAPSHOT_BYTE_LIMITS[PurchaseOrder::class] + 1)],
                'occurred_at' => now(),
            ]);
            $this->fail('Oversized revision unexpectedly succeeded.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Audit revision snapshot exceeds its size limit.', $exception->getMessage());
        }

        $revision = AuditRevision::create([
            'activity_id' => $activity->id,
            'module' => AuditModule::FoodServiceOperations,
            'domain' => AuditDomain::Procurement,
            'subject_type' => PurchaseOrder::class,
            'subject_public_id' => $activity->subject_public_id,
            'action' => AuditAction::Updated,
            'schema_version' => 1,
            'after' => ['status' => 'draft'],
            'occurred_at' => now(),
        ]);

        try {
            DB::table('audit_revisions')->where('id', $revision->id)->update(['schema_version' => 2]);
            $this->fail('Direct revision update unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit events may only be mutated by the retention service.', $exception->getMessage());
        }

        AuditFixture::delete(AuditActivity::query()->whereKey($activity->id));
        $this->assertDatabaseMissing('audit_revisions', ['id' => $revision->id]);

        $patientLinked = $this->legacyActivity([
            'category' => 'clinical',
            'domain' => 'nutrition_library',
            'module' => AuditModule::NutritionCare->value,
            'subject_type' => Recipe::class,
            'subject_public_id' => (string) Str::uuid(),
            'root_patient_id' => 999999,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Patient-linked audit events cannot have revisions.');
        AuditRevision::create([
            'activity_id' => $patientLinked->id,
            'module' => AuditModule::NutritionCare,
            'domain' => AuditDomain::NutritionLibrary,
            'subject_type' => Recipe::class,
            'subject_public_id' => $patientLinked->subject_public_id,
            'action' => AuditAction::Updated,
            'schema_version' => 1,
            'after' => ['name' => 'Forbidden patient-linked recipe'],
            'occurred_at' => now(),
        ]);
    }

    public function test_three_migrations_round_trip_on_isolated_mysql(): void
    {
        $database = 'nutriscope_audit_redesign_'.Str::lower(Str::random(10));
        $admin = 'audit_redesign_admin';
        $connection = 'audit_redesign_migration';
        $base = config('database.connections.mysql');
        $originalDefault = config('database.default');
        $originalActivity = config('activitylog.database_connection');

        config([
            "database.connections.{$admin}" => [...$base, 'url' => null, 'database' => 'information_schema'],
            "database.connections.{$connection}" => [...$base, 'url' => null, 'database' => $database],
        ]);

        try {
            DB::connection($admin)->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            config(['database.default' => $connection, 'activitylog.database_connection' => $connection]);
            $schema = Schema::connection($connection);
            $this->createPreRedesignSchema($schema);

            $ddl = require database_path('migrations/2026_07_15_100001_add_module_and_patient_snapshot_to_activity_log.php');
            $revisions = require database_path('migrations/2026_07_15_100002_create_audit_revisions_table.php');
            $backfill = require database_path('migrations/2026_07_15_100003_backfill_audit_modules_and_patient_snapshots.php');

            $ddl->up();
            $revisions->up();
            $backfill->up();
            $this->assertTrue($schema->hasColumn('activity_log', 'module'));
            $this->assertTrue($schema->hasTable('audit_revisions'));

            $backfill->down();
            $revisions->down();
            $ddl->down();
            $this->assertFalse($schema->hasColumn('activity_log', 'module'));
            $this->assertFalse($schema->hasTable('audit_revisions'));
            $this->assertTrue($schema->hasColumn('activity_log', 'domain'));

            $ddl->up();
            $revisions->up();
            $backfill->up();
            $this->assertTrue($schema->hasColumn('activity_log', 'patient_display_name_snapshot'));
            $this->assertTrue($schema->hasTable('audit_revisions'));
        } finally {
            config(['database.default' => $originalDefault, 'activitylog.database_connection' => $originalActivity]);
            DB::purge($connection);
            DB::connection($admin)->statement("DROP DATABASE IF EXISTS `{$database}`");
            DB::purge($admin);
        }
    }

    private function legacyActivity(array $attributes = []): AuditActivity
    {
        return AuditActivity::create([
            'log_name' => config('audit.log_name'),
            'description' => 'Legacy audit event',
            'event' => 'updated',
            'properties' => ['details' => []],
            ...$attributes,
        ]);
    }

    private function createPreRedesignSchema($schema): void
    {
        $schema->create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->nullable()->unique();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->string('category', 32)->nullable();
            $table->string('domain', 32)->nullable();
            $table->string('severity', 16)->nullable();
            $table->string('outcome', 16)->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->unsignedBigInteger('root_patient_id')->nullable();
            $table->unsignedBigInteger('ncp_record_id')->nullable();
            $table->unsignedBigInteger('audit_owner_id')->nullable();
            $table->uuid('subject_public_id')->nullable();
            $table->uuid('context_public_id')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });
        $schema->create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });
        $schema->create('ncp_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('patient_id');
        });
    }
}
