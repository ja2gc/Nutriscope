<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Models\AuditActivity;
use App\Models\FoodItem;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLegacyCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_retired_category_and_domain_list_parameters_are_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?category=operations')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?domain=reports')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
    }

    public function test_filter_metadata_exposes_only_active_module_taxonomy(): void
    {
        config()->set('audit.features.export', false);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs')
            ->assertOk();

        $filters = $response->json('meta.filters');
        $this->assertIsArray($filters);
        $this->assertArrayNotHasKey('categories', $filters);
        $this->assertArrayNotHasKey('domains', $filters);
        $this->assertArrayNotHasKey('category_actions', $filters);
        $this->assertNotContains(AuditAction::Exported->value, collect($filters['actions'])->pluck('value')->all());
        $this->assertArrayNotHasKey('export', $response->json('meta.capabilities'));
    }

    public function test_mixed_current_and_legacy_rows_remain_readable_without_reclassifying_unknown_actions(): void
    {
        $legacy = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Historical custom event',
            'event' => 'Legacy.Custom-Action',
            'properties' => [],
        ]);
        $current = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Current report event',
            'event' => AuditAction::Generated,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::Reports,
            'module' => AuditModule::Reports,
            'properties' => [],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs')
            ->assertOk();

        $events = collect($response->json('data'));
        $legacyDto = $events->firstWhere('id', $legacy->public_id);
        $currentDto = $events->firstWhere('id', $current->public_id);

        $this->assertSame('legacy_unclassified', $legacyDto['module']);
        $this->assertSame('legacy_unclassified', $legacyDto['category']);
        $this->assertSame('legacy_unclassified', $legacyDto['domain']);
        $this->assertSame('legacy.custom-action', $legacyDto['action']);
        $this->assertSame('Legacy custom action', $legacyDto['action_label']);
        $this->assertSame('reports', $currentDto['module']);
    }

    public function test_account_activation_changes_use_block_and_unblock_actions_while_deletion_stays_deleted(): void
    {
        $user = User::factory()->create(['role' => 'RND', 'is_active' => true]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", ['is_active' => false])
            ->assertOk();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'event' => AuditAction::AccountBlocked->value,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", ['is_active' => true])
            ->assertOk();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'event' => AuditAction::AccountUnblocked->value,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->uuid}")
            ->assertNoContent();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'event' => AuditAction::Deleted->value,
        ]);
    }

    public function test_backfill_is_chunked_idempotent_and_preserves_privacy_and_existing_history(): void
    {
        $patient = Patient::factory()->create([
            'first_name' => 'Maria Luisa',
            'last_name' => 'Santos-Cruz',
            'name' => 'Maria Luisa Santos-Cruz',
        ]);
        $patientEvent = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy patient event',
            'event' => AuditAction::Updated,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Patients,
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'properties' => ['details' => ['ncp_reference' => 'NCP-AB12CD34']],
        ]);
        $unresolved = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Unresolved legacy patient event',
            'event' => AuditAction::Updated,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Ncp,
            'subject_type' => Patient::class,
            'subject_id' => 999999999,
            'properties' => ['details' => ['ncp_reference' => 'NCP-A1B2C3D4E5F60708']],
        ]);
        $food = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy RND food event',
            'event' => AuditAction::Created,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::FoodService,
            'module' => AuditModule::FoodServiceOperations,
            'subject_type' => FoodItem::class,
            'subject_id' => 1001,
            'properties' => [],
        ]);
        $recipe = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy RND recipe event',
            'event' => AuditAction::Created,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::FoodService,
            'subject_type' => Recipe::class,
            'subject_id' => 1002,
            'properties' => [],
        ]);
        $ambiguous = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Ambiguous legacy event',
            'event' => 'legacy_unknown',
            'properties' => [],
        ]);
        $existingSnapshot = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Already snapshotted event',
            'event' => AuditAction::Viewed,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Patients,
            'module' => AuditModule::NutritionCare,
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'patient_display_name_snapshot' => 'Historical Patient',
            'properties' => [],
        ]);

        $this->artisan('audit:backfill-oversight', ['--chunk' => 1])->assertSuccessful();
        $firstState = DB::table('activity_log')
            ->whereIn('id', [$patientEvent->id, $unresolved->id, $food->id, $recipe->id, $ambiguous->id, $existingSnapshot->id])
            ->orderBy('id')
            ->get(['id', 'module', 'domain', 'patient_display_name_snapshot', 'properties'])
            ->map(fn (object $row): array => (array) $row)
            ->all();
        $this->artisan('audit:backfill-oversight', ['--chunk' => 2])->assertSuccessful();
        $secondState = DB::table('activity_log')
            ->whereIn('id', [$patientEvent->id, $unresolved->id, $food->id, $recipe->id, $ambiguous->id, $existingSnapshot->id])
            ->orderBy('id')
            ->get(['id', 'module', 'domain', 'patient_display_name_snapshot', 'properties'])
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->assertSame($firstState, $secondState);
        $this->assertSame(AuditModule::NutritionCare, $patientEvent->refresh()->module);
        $this->assertSame($patient->display_name, $patientEvent->patient_display_name_snapshot);
        $ciphertext = DB::table('activity_log')->where('id', $patientEvent->id)->value('patient_display_name_snapshot');
        $this->assertIsString($ciphertext);
        $this->assertNotSame($patient->display_name, $ciphertext);
        $this->assertNull($unresolved->refresh()->patient_display_name_snapshot);
        $this->assertSame('NCP-A1B2C3D4E5F60708', $unresolved->properties['details']['ncp_reference']);
        $this->assertSame(
            'NCP-A1B2C3D4E5F60708',
            app(AuditEventPresenter::class)->present($unresolved)->ncpReference,
        );
        $this->assertSame(AuditModule::NutritionCare, $food->refresh()->module);
        $this->assertSame(AuditDomain::NutritionLibrary, $food->domain);
        $this->assertSame(AuditModule::NutritionCare, $recipe->refresh()->module);
        $this->assertSame(AuditDomain::NutritionLibrary, $recipe->domain);
        $this->assertNull($ambiguous->refresh()->module);
        $this->assertSame('legacy_unclassified', app(AuditEventPresenter::class)->present($ambiguous)->module);
        $this->assertSame('Historical Patient', $existingSnapshot->refresh()->patient_display_name_snapshot);
        $this->assertDatabaseCount('audit_revisions', 0);
        $this->assertStringNotContainsString($patient->display_name, json_encode($secondState, JSON_THROW_ON_ERROR));
    }
}
