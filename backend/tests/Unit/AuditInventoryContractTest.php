<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AuditInventoryContractTest extends TestCase
{
    private const CHARACTERIZATION_SUITES = [
        'tests/Feature/Audit/AuditContractTest.php' => [
            'test_legacy_metadata_is_explicitly_unclassified_only_when_presented',
            'test_metadata_migration_is_reversible',
        ],
        'tests/Feature/Audit/StructuredAuditApiTest.php' => [
            'test_admin_list_returns_only_the_structured_public_contract',
            'test_common_audit_queries_have_explainable_indexed_plans',
            'test_legacy_unclassified_rows_and_action_aliases_remain_readable',
        ],
        'tests/Feature/Audit/AuditRouteCoverageTest.php' => [
            'test_every_unsafe_route_has_an_audit_classification_and_reason',
        ],
        'tests/Feature/Audit/AuditPrivacyTest.php' => [
            'test_nested_forbidden_keys_and_format_variants_are_removed',
            'test_audit_logger_is_the_only_production_activity_writer',
        ],
        'tests/Feature/Audit/ClinicalTrailTest.php' => [
            'test_every_clinical_model_create_update_delete_is_rooted_and_phi_free',
            'test_every_rnd_can_open_another_rnds_chart_and_patient_trail',
        ],
        'tests/Feature/Audit/PurchaseOrderTrailTest.php' => [
            'test_po_update_delete_and_archive_emit_one_rich_event_without_model_duplicates',
            'test_completion_ledger_and_audit_roll_back_together_when_audit_is_unavailable',
        ],
        'tests/Feature/Audit/SecurityAuditTest.php' => [
            'test_actual_named_limiter_429_is_logged_once_without_request_secrets',
            'test_legacy_login_rows_are_presented_as_login_succeeded',
        ],
        'tests/Feature/Audit/ReportAuditTest.php' => [
            'test_report_views_downloads_and_deletes_emit_safe_semantic_events',
            "->assertJsonPath('data.0.actor.id', \$other->uuid)",
        ],
        'tests/Feature/Audit/SharedRndClinicalAccessTest.php' => [
            'test_an_rnd_can_write_every_clinical_section_of_another_rnds_ncp',
            'test_an_rnd_can_delete_another_rnds_draft_ncp',
            'test_patient_rows_and_ncp_cards_identify_creator_and_latest_clinical_actor',
        ],
        'tests/Feature/OperationsAuditTest.php' => [
            'test_supplier_fs_item_and_shopping_list_crud_emit_exactly_one_event_per_mutation',
            'test_audit_failure_rolls_back_simple_and_nested_mutations',
        ],
        'tests/Feature/BudgetAuditTest.php' => [
            'test_budget_setup_and_manual_adjustment_write_audit_events',
            'test_po_deduction_ledger_creation_writes_system_audit_event',
        ],
        'tests/Feature/Audit/AuditLegacyCompatibilityTest.php' => [
            'test_retired_category_and_domain_list_parameters_are_rejected',
            'test_backfill_is_chunked_idempotent_and_preserves_privacy_and_existing_history',
        ],
    ];

    private const AUDIT_LOGGER_REFERENCES = [
        'app/Actions/Audit/SetAuditRetentionState.php',
        'app/Console/Commands/PruneAuditEvents.php',
        'app/Http/Controllers/ActivityController.php',
        'app/Http/Controllers/Admin/AiUsageLimitController.php',
        'app/Http/Controllers/Admin/AnnouncementController.php',
        'app/Http/Controllers/Admin/AuditLogController.php',
        'app/Http/Controllers/Admin/AuditLogExportController.php',
        'app/Http/Controllers/Admin/UserController.php',
        'app/Http/Controllers/Auth/AuthController.php',
        'app/Http/Controllers/Auth/PasswordResetController.php',
        'app/Http/Controllers/Auth/RecoveryEmailController.php',
        'app/Http/Controllers/Controller.php',
        'app/Http/Controllers/FSS/BudgetController.php',
        'app/Http/Controllers/FSS/DietListCountController.php',
        'app/Http/Controllers/FSS/FoodServiceRecipeController.php',
        'app/Http/Controllers/FSS/FoodServiceSettingController.php',
        'app/Http/Controllers/FSS/FsItemController.php',
        'app/Http/Controllers/FSS/MealPrepLogController.php',
        'app/Http/Controllers/FSS/MenuCycleController.php',
        'app/Http/Controllers/FSS/MenuCycleTemplateController.php',
        'app/Http/Controllers/FSS/PurchaseOrderController.php',
        'app/Http/Controllers/FSS/ShoppingListController.php',
        'app/Http/Controllers/FSS/SupplierController.php',
        'app/Http/Controllers/ReportBrandingController.php',
        'app/Http/Controllers/ReportController.php',
        'app/Http/Controllers/ReportTemplateController.php',
        'app/Http/Controllers/RND/AiDiagnosisController.php',
        'app/Http/Controllers/RND/AnnouncementController.php',
        'app/Http/Controllers/RND/AssessmentController.php',
        'app/Http/Controllers/RND/FoodItemController.php',
        'app/Http/Controllers/RND/MealPlanController.php',
        'app/Http/Controllers/RND/MealPlanItemController.php',
        'app/Http/Controllers/RND/PatientController.php',
        'app/Http/Controllers/RND/RecipeController.php',
        'app/Http/Controllers/RND/ScreeningDocumentController.php',
        'app/Http/Controllers/RND/UsdaController.php',
        'app/Http/Controllers/SopController.php',
        'app/Jobs/GenerateReport.php',
        'app/Listeners/BudgetLedgerListener.php',
        'app/Services/Audit/AuditLogger.php',
        'app/Services/Audit/SecurityAuditDeduplicator.php',
        'app/Services/FSS/AccomplishmentReportArchiveService.php',
        'app/Services/FSS/PurchaseOrderLifecycleService.php',
        'app/Services/FSS/ReceivingService.php',
    ];

    private const AUDITED_MODELS = [
        'app/Models/Assessment.php',
        'app/Models/Diagnosis.php',
        'app/Models/FoodServiceRecipe.php',
        'app/Models/FsItem.php',
        'app/Models/Intervention.php',
        'app/Models/MealPlan.php',
        'app/Models/MealPrepLog.php',
        'app/Models/MenuCycle.php',
        'app/Models/Monitoring.php',
        'app/Models/NcpRecord.php',
        'app/Models/Patient.php',
        'app/Models/PurchaseOrder.php',
        'app/Models/ScreeningDocument.php',
        'app/Models/ShoppingList.php',
    ];

    private const READ_SENSITIVE_ROUTES = [
        'GET|HEAD api/admin/audit-logs',
        'GET|HEAD api/admin/audit-logs/export',
        'GET|HEAD api/admin/budgets/{budget}/activity',
        'GET|HEAD api/admin/reports/{report}/activity',
        'GET|HEAD api/admin/reports/{report}/download',
        'GET|HEAD api/admin/reports/{report}/view',
        'GET|HEAD api/admin/reports/{type}/export',
        'GET|HEAD api/admin/reports/{type}/render',
        'GET|HEAD api/fss/budgets/{budget}/activity',
        'GET|HEAD api/fss/purchase-orders/{purchase_order}/activity',
        'GET|HEAD api/fss/reports/{report}/download',
        'GET|HEAD api/fss/reports/{report}/view',
        'GET|HEAD api/fss/reports/{type}/export',
        'GET|HEAD api/fss/reports/{type}/render',
        'GET|HEAD api/rnd/ncp-records/{ncpRecord}/activity',
        'GET|HEAD api/rnd/patients/{patient}',
        'GET|HEAD api/rnd/patients/{patient}/activity',
        'GET|HEAD api/rnd/reports/{report}/activity',
        'GET|HEAD api/rnd/reports/{report}/download',
        'GET|HEAD api/rnd/reports/{report}/view',
        'GET|HEAD api/rnd/reports/{type}/export',
        'GET|HEAD api/rnd/reports/{type}/render',
        'GET|HEAD api/rnd/screening-documents/{screeningDocument}/file',
    ];

    private const AUDIT_MIGRATIONS = [
        '2026_05_23_211659_create_activity_log_table.php',
        '2026_05_23_211700_add_event_column_to_activity_log_table.php',
        '2026_05_23_211701_add_batch_uuid_column_to_activity_log_table.php',
        '2026_07_11_000001_add_metadata_and_indexes_to_activity_log_table.php',
        '2026_07_12_000002_add_clinical_root_indexes_to_activity_log_table.php',
        '2026_07_12_090108_add_actor_query_index_to_activity_log_table.php',
        '2026_07_12_092936_add_public_id_to_activity_log_table.php',
        '2026_07_12_092937_backfill_activity_log_public_ids.php',
        '2026_07_12_095710_add_public_references_to_activity_log_table.php',
        '2026_07_12_095711_backfill_activity_log_public_references.php',
        '2026_07_14_000001_create_audit_settings_table.php',
        '2026_07_15_100001_add_module_and_patient_snapshot_to_activity_log.php',
        '2026_07_15_100002_create_audit_revisions_table.php',
        '2026_07_15_100003_backfill_audit_modules_and_patient_snapshots.php',
    ];

    public function test_every_audit_logger_reference_and_model_observer_is_classified(): void
    {
        $loggerReferences = self::AUDIT_LOGGER_REFERENCES;
        sort($loggerReferences);

        $this->assertSame($loggerReferences, $this->filesContaining(app_path(), 'AuditLogger'));
        $this->assertSame(self::AUDITED_MODELS, $this->filesContaining(app_path('Models'), 'use AuditsChanges'));
    }

    public function test_every_required_characterization_suite_keeps_its_baseline_contracts(): void
    {
        foreach (self::CHARACTERIZATION_SUITES as $path => $needles) {
            $source = file_get_contents(base_path($path));
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $source, $path);
            }
        }
    }

    public function test_mutation_and_read_sensitive_route_inventories_are_explicit(): void
    {
        $routeList = new Process(
            [PHP_BINARY, base_path('artisan'), 'route:list', '--json'],
            base_path(),
            ['APP_ENV' => 'local'],
        );
        $routeList->mustRun();
        $routes = json_decode($routeList->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $unsafe = collect($routes)
            ->filter(fn (array $route): bool => collect(explode('|', $route['method']))
                ->contains(fn (string $method): bool => ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)))
            ->map(fn (array $route): string => "{$route['method']} {$route['uri']}")
            ->sort()
            ->values()
            ->all();
        $classifiedUnsafe = array_keys(config('audit.route_coverage'));
        sort($classifiedUnsafe);
        $this->assertSame($classifiedUnsafe, $unsafe);

        $allRoutes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->all();
        foreach (self::READ_SENSITIVE_ROUTES as $route) {
            $this->assertContains($route, $allRoutes);
        }
    }

    public function test_infrastructure_schedulers_migrations_and_seed_boundaries_are_inventoried(): void
    {
        foreach ([
            'app/Models/AuditActivity.php',
            'app/Models/Concerns/AuditsChanges.php',
            'app/Services/Audit/AuditContextResolver.php',
            'app/Services/Audit/AuditQuery.php',
            'app/Services/Audit/AuditEventPresenter.php',
            'app/Services/Audit/AuditOversightBackfill.php',
            'app/Services/Audit/LegacyAuditEntityLabels.php',
            'app/Console/Commands/BackfillAuditOversight.php',
            'app/Services/Audit/AuditFilterMetadata.php',
            'app/Policies/AuditPolicy.php',
            'app/Http/Controllers/Admin/AuditLogController.php',
            'app/Http/Controllers/Admin/AuditLogExportController.php',
            'app/Http/Controllers/Admin/AuditRetentionController.php',
        ] as $path) {
            $this->assertFileExists(base_path($path));
        }

        $migrations = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $path): string => basename($path))
            ->filter(fn (string $file): bool => str_contains($file, 'activity_log')
                || str_contains($file, 'audit_settings')
                || str_contains($file, 'audit_revisions')
                || str_contains($file, 'audit_modules'))
            ->sort()
            ->values()
            ->all();
        $this->assertSame(self::AUDIT_MIGRATIONS, $migrations);

        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        foreach (['audit:prune --force', 'audit:monitor-health', 'audit:emit-monthly-metrics'] as $scheduled) {
            $this->assertStringContainsString($scheduled, $bootstrap);
        }
        $this->assertStringContainsString('AuditRetentionState::class)->enabled()', $bootstrap);
        $this->assertStringNotContainsString("schedule->command('audit:backfill-oversight", $bootstrap);
        $this->assertArrayHasKey('audit:backfill-oversight', Artisan::all());

        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString('BudgetLedgerListener::class', $provider);
        $this->assertStringContainsString('PurchaseOrderCompleted::class', $provider);

        $databaseSeeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringContainsString('activity()->withoutLogs', $databaseSeeder);
        $this->assertFileDoesNotExist(database_path('seeders/DemoAuditSeeder.php'));
        $this->assertFileDoesNotExist(database_path('seeders/AuditDemoSeeder.php'));
    }

    public function test_additive_redesign_storage_contract_is_implemented(): void
    {
        $this->assertTrue(class_exists('App\\Enums\\AuditModule'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'module'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'patient_display_name_snapshot'));
        $this->assertTrue(Schema::hasTable('audit_revisions'));
    }

    /** @return list<string> */
    private function filesContaining(string $root, string $needle): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if (str_contains(file_get_contents($file->getPathname()), $needle)) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            }
        }

        sort($files);

        return $files;
    }
}
