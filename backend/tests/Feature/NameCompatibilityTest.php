<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Models\AuditActivity;
use App\Models\Patient;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NameCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_compound_user_name_round_trips_exactly(): void
    {
        $user = User::factory()->create(['name' => 'Maria Luisa De la Cruz']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('name', 'Maria Luisa De la Cruz');

        $this->assertSame('Maria Luisa De la Cruz', $user->fresh()->name);
    }

    public function test_legacy_compound_patient_name_and_unrelated_edit_round_trip_exactly(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create(['name' => 'Juan Miguel Dela Cruz III']);

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/patients/{$patient->uuid}", ['ward' => 'Ward 7'])
            ->assertOk()
            ->assertJsonPath('name', 'Juan Miguel Dela Cruz III')
            ->assertJsonPath('ward', 'Ward 7');

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'name' => 'Juan Miguel Dela Cruz III',
            'ward' => 'Ward 7',
        ]);
    }

    public function test_soft_deleted_user_retains_exact_legacy_name(): void
    {
        $user = User::factory()->create(['name' => 'Ana Mae San Jose']);

        $user->delete();

        $this->assertSame('Ana Mae San Jose', User::withTrashed()->findOrFail($user->id)->name);
    }

    public function test_existing_actor_snapshot_does_not_follow_later_user_rename(): void
    {
        $actor = User::factory()->admin()->create(['name' => 'Original Actor']);
        $subject = User::factory()->rnd()->create();

        $this->actingAs($actor, 'sanctum');
        app(AuditLogger::class)->record(
            AuditAction::Updated,
            AuditCategory::Operations,
            AuditDomain::Accounts,
            subject: $subject,
            details: ['changed_fields' => ['role']],
        );

        $actor->forceFill(['name' => 'Renamed Actor'])->save();

        $snapshot = AuditActivity::query()->sole()->properties['actor'];
        $this->assertSame('Original Actor', $snapshot['name']);
    }

    public function test_existing_prepared_by_snapshot_does_not_follow_later_user_rename(): void
    {
        $preparer = User::factory()->rnd()->create(['name' => 'Original Preparer']);
        $report = Report::create([
            'user_id' => $preparer->id,
            'title' => 'Frozen report',
            'type' => 'accomplishment_report',
            'status' => 'archived',
            'snapshot' => ['prepared_by_name' => 'Original Preparer'],
        ]);

        $preparer->forceFill(['name' => 'Renamed Preparer'])->save();

        $this->assertSame('Original Preparer', $report->fresh()->snapshot['prepared_by_name']);
    }
}
