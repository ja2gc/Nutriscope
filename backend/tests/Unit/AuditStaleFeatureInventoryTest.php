<?php

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Http\Controllers\FSS\InventoryController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditStaleFeatureInventoryTest extends TestCase
{
    private const DECISIONS = [
        'category tabs' => ['remove', 6],
        'normal Domain filter' => ['remove', 6],
        'category request parameter' => ['remove-after-compatibility', 15],
        'domain request parameter' => ['remove-after-compatibility', 15],
        'legacy category and domain storage' => ['retain', 15],
        'unknown action mapped to Updated' => ['remove', 15],
        'disabled export UI and active capability' => ['remove-after-compatibility', 15],
        'report creator ownership gates' => ['remove', 13],
        'IP-blocking scaffolding' => ['keep-absent', 15],
        'account block and unblock actions' => ['retain', 15],
        'raw JSON audit rendering' => ['keep-absent', 15],
        'quantity_in_stock live surface' => ['already-retired', 15],
        'read-only derived inventory routes' => ['retain', 15],
        'historical inventory migrations' => ['retain', 15],
        'audit-related dead controllers models or migrations' => ['keep-absent', 15],
        'base seeder anonymous audit noise' => ['keep-absent', 14],
        'dedicated demo audit seeder' => ['add', 14],
        'stale four-view audit documentation' => ['update', 17],
    ];

    public function test_every_stale_or_compatibility_item_has_an_explicit_decision_and_owner_task(): void
    {
        $this->assertSame([
            'category tabs',
            'normal Domain filter',
            'category request parameter',
            'domain request parameter',
            'legacy category and domain storage',
            'unknown action mapped to Updated',
            'disabled export UI and active capability',
            'report creator ownership gates',
            'IP-blocking scaffolding',
            'account block and unblock actions',
            'raw JSON audit rendering',
            'quantity_in_stock live surface',
            'read-only derived inventory routes',
            'historical inventory migrations',
            'audit-related dead controllers models or migrations',
            'base seeder anonymous audit noise',
            'dedicated demo audit seeder',
            'stale four-view audit documentation',
        ], array_keys(self::DECISIONS));

        foreach (self::DECISIONS as [$decision, $task]) {
            $this->assertContains($decision, ['remove', 'remove-after-compatibility', 'retain', 'keep-absent', 'already-retired', 'add', 'update']);
            $this->assertContains($task, [6, 13, 14, 15, 17]);
        }
    }

    public function test_current_compatibility_and_stale_paths_match_the_inventory(): void
    {
        $request = file_get_contents(app_path('Http/Requests/Admin/ListAuditLogsRequest.php'));
        $presenter = file_get_contents(app_path('Services/Audit/AuditEventPresenter.php'));
        $reportController = file_get_contents(app_path('Http/Controllers/ReportController.php'));
        $reportBrowser = file_get_contents(app_path('Services/Reports/ReportBrowser.php'));
        $auditPolicy = file_get_contents(app_path('Policies/AuditPolicy.php'));
        $auditPage = file_get_contents(base_path('../frontend/app/admin/audit-logs/page.tsx'));
        $filters = file_get_contents(base_path('../frontend/components/audit/AuditFilters.tsx'));
        $auditService = file_get_contents(base_path('../frontend/services/auditLogService.ts'));

        $this->assertStringContainsString("'category'", $request);
        $this->assertStringContainsString("'domain'", $request);
        $this->assertStringNotContainsString('?? AuditAction::Updated', $presenter);
        $this->assertStringContainsString('AuditAction::tryFrom($candidate)', $presenter);
        $this->assertStringContainsString('return [AuditAction::Updated->value', $presenter);
        $this->assertStringContainsString('authorizeReportAccess($report)', $reportController);
        $this->assertStringNotContainsString('authorizeOwner', $reportController);
        $this->assertStringContainsString("\$role !== 'RND'", $reportController);
        $this->assertStringContainsString("where('user_id', Auth::id())", $reportController);
        $this->assertStringNotContainsString("where('rnd_user_id', Auth::id())", $reportBrowser);
        $this->assertStringNotContainsString('$report->user_id === $user->id', $auditPolicy);
        $this->assertStringContainsString('AuditModule', $auditPage);
        $this->assertStringContainsString('Security & Administration', $auditPage);
        $this->assertStringNotContainsString('AuditCategory', $auditPage);
        $this->assertStringNotContainsString('label="Domain"', $filters);
        $this->assertStringContainsString('qs.set("category"', $auditService);
        $this->assertStringContainsString('qs.set("domain"', $auditService);
        $this->assertFalse(config('audit.features.export'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'category'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'domain'));
        $this->assertFileExists(base_path('../frontend/components/audit/AuditExportButton.tsx'));
        $this->assertStringContainsString(
            'four UI views only',
            file_get_contents(base_path('../docs/architecture/audit-logging.md')),
        );
        $this->assertStringContainsString(
            'activity()->withoutLogs',
            file_get_contents(database_path('seeders/DatabaseSeeder.php')),
        );
        $this->assertFileDoesNotExist(database_path('seeders/AuditDemoSeeder.php'));
    }

    public function test_ip_blocking_and_raw_json_remain_absent_while_account_controls_remain(): void
    {
        $productionRoots = [
            app_path(),
            config_path(),
            database_path('migrations'),
            base_path('routes'),
            base_path('../frontend/app'),
            base_path('../frontend/components'),
            base_path('../frontend/services'),
            base_path('../frontend/types'),
        ];
        $ipPattern = '/AUDIT_SECURITY_BLOCKS_ENABLED|IpBlock(?:ed|ing)?|BlockedIp|blocked_ip|ip[-_ ]block(?:ed|ing)?|temporary_ip_block|security-blocks/i';
        $matches = collect($productionRoots)
            ->flatMap(fn (string $root) => File::exists($root) ? File::allFiles($root) : [])
            ->filter(fn (\SplFileInfo $file): bool => ! str_contains($file->getPathname(), '.test.')
                && (preg_match($ipPattern, $file->getPathname()) === 1 || preg_match($ipPattern, File::get($file->getPathname())) === 1))
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->all();

        $this->assertSame([], $matches);
        $this->assertContains(AuditAction::AccountBlocked, AuditAction::cases());
        $this->assertContains(AuditAction::AccountUnblocked, AuditAction::cases());

        $auditUi = collect(File::allFiles(base_path('../frontend/components/audit')))
            ->reject(fn (\SplFileInfo $file): bool => str_contains($file->getFilename(), '.test.'))
            ->map(fn (\SplFileInfo $file): string => File::get($file->getPathname()))
            ->implode("\n");
        $this->assertStringNotContainsString('JSON.stringify', $auditUi);
        $this->assertStringNotContainsString('<pre', $auditUi);
    }

    public function test_retired_stock_fields_have_no_live_consumer_and_inventory_remains_read_only(): void
    {
        $this->assertFalse(Schema::hasColumn('inventory', 'quantity_in_stock'));
        $this->assertFileExists(database_path('migrations/2026_06_02_210751_create_inventory_table.php'));
        $this->assertFileExists(database_path('migrations/2026_07_11_000004_drop_retired_inventory_stock_fields.php'));
        $this->assertTrue(class_exists(InventoryController::class));

        $routes = collect(Route::getRoutes()->getRoutes());
        $inventory = $routes->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/fss/inventory'));
        $this->assertNotEmpty($inventory);
        $this->assertTrue($inventory->every(fn ($route): bool => collect($route->methods())->every(
            fn (string $method): bool => in_array($method, ['GET', 'HEAD'], true),
        )));

        foreach ([
            app_path(),
            base_path('../frontend/app'),
            base_path('../frontend/components'),
            base_path('../frontend/services'),
            base_path('../frontend/types'),
            base_path('../mobile/app'),
            base_path('../mobile/lib'),
        ] as $root) {
            $matches = collect(File::allFiles($root))
                ->filter(fn (\SplFileInfo $file): bool => str_contains(File::get($file->getPathname()), 'quantity_in_stock'));
            $this->assertCount(0, $matches, $root);
        }
    }

    public function test_api_compatibility_remains_while_unknown_actions_use_the_typed_presenter(): void
    {
        $request = file_get_contents(app_path('Http/Requests/Admin/ListAuditLogsRequest.php'));
        $presenter = file_get_contents(app_path('Services/Audit/AuditEventPresenter.php'));

        $this->assertStringContainsString("'category'", $request);
        $this->assertStringContainsString("'domain'", $request);
        $this->assertStringNotContainsString('?? AuditAction::Updated', $presenter);
        $this->assertStringContainsString('AuditAction::tryFrom($candidate)', $presenter);
    }
}
