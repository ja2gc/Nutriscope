<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Resources\PatientResource;
use App\Http\Resources\UserResource;
use App\Models\Announcement;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\Patient;
use App\Models\Report;
use App\Models\Sop;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonNameBackendFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_create_requires_split_names_and_split_input_wins(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'Deprecated Only',
                'email' => 'legacy-create@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'RND',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name']);

        $this->postJson('/api/admin/users', [
            'first_name' => "Control\nName",
            'last_name' => 'Rejected',
            'email' => 'control-name@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'RND',
        ])->assertUnprocessable()->assertJsonValidationErrors(['first_name']);

        $this->postJson('/api/admin/users', [
            'first_name' => str_repeat('a', 256),
            'last_name' => 'Rejected',
            'email' => 'long-name@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'RND',
        ])->assertUnprocessable()->assertJsonValidationErrors(['first_name']);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Wrong Deprecated Value',
            'first_name' => '  Maria   Luisa ',
            'last_name' => ' De la   Cruz ',
            'email' => 'split-create@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'RND',
        ])->assertCreated();

        $response
            ->assertJsonPath('data.first_name', 'Maria Luisa')
            ->assertJsonPath('data.last_name', 'De la Cruz')
            ->assertJsonPath('data.display_name', 'Maria Luisa De la Cruz')
            ->assertJsonPath('data.name', 'Maria Luisa De la Cruz');

        $this->assertDatabaseHas('users', [
            'email' => 'split-create@example.com',
            'first_name' => 'Maria Luisa',
            'last_name' => 'De la Cruz',
            'name' => 'Maria Luisa De la Cruz',
        ]);
    }

    public function test_admin_update_uses_existing_split_part_and_rejects_legacy_only_rename(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->rnd()->create([
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", ['first_name' => ' Maria Luisa '])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Maria Luisa')
            ->assertJsonPath('data.last_name', 'Santos')
            ->assertJsonPath('data.name', 'Maria Luisa Santos');

        $activity = AuditActivity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', AuditAction::Updated->value)
            ->sole();
        $this->assertSame('Maria', $activity->properties['old']['first_name']);
        $this->assertSame('Maria Luisa', $activity->properties['attributes']['first_name']);
        $this->assertSame([
            'field' => 'first_name',
            'label' => 'First Name',
            'old_value' => 'Maria',
            'new_value' => 'Maria Luisa',
            'redacted' => false,
        ], collect(app(AuditEventPresenter::class)->present($activity)->changes)->firstWhere('field', 'first_name'));

        $legacy = User::factory()->rnd()->create([
            'name' => 'Legacy Mononym',
            'first_name' => 'Legacy Mononym',
            'last_name' => null,
        ]);

        $this->patchJson("/api/admin/users/{$legacy->uuid}", ['name' => 'Renamed Legacy'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name']);

        $this->patchJson("/api/admin/users/{$legacy->uuid}", ['role' => 'FSS'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Legacy Mononym');
    }

    public function test_profile_name_change_and_unrelated_legacy_edit_follow_the_pair_contract(): void
    {
        $user = User::factory()->rnd()->create([
            'name' => 'Juan Santos',
            'first_name' => 'Juan',
            'last_name' => 'Santos',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', ['first_name' => ' Juan Miguel '])
            ->assertOk()
            ->assertJsonPath('first_name', 'Juan Miguel')
            ->assertJsonPath('last_name', 'Santos')
            ->assertJsonPath('display_name', 'Juan Miguel Santos')
            ->assertJsonPath('name', 'Juan Miguel Santos');

        $activity = AuditActivity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', AuditAction::ProfileChanged->value)
            ->sole();
        $this->assertSame('Juan', $activity->properties['old']['first_name']);
        $this->assertSame('Juan Miguel', $activity->properties['attributes']['first_name']);

        $legacy = User::factory()->rnd()->create([
            'name' => 'Legacy Profile',
            'first_name' => 'Legacy Profile',
            'last_name' => null,
        ]);

        $this->actingAs($legacy, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'name' => 'Legacy Profile',
                'contact_number' => '09170000000',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Legacy Profile')
            ->assertJsonPath('contact_number', '09170000000');
    }

    public function test_patient_create_update_and_resource_use_the_split_contract(): void
    {
        $rnd = User::factory()->rnd()->create();
        $base = [
            'dob' => '1995-05-05',
            'sex' => 'Female',
            'admission_date' => '2026-07-15',
        ];

        $this->actingAs($rnd, 'sanctum')
            ->postJson('/api/rnd/patients', ['name' => 'Deprecated Only', ...$base])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name']);

        $response = $this->postJson('/api/rnd/patients', [
            'name' => 'Wrong Deprecated Value',
            'first_name' => ' Ana   Mae ',
            'last_name' => ' San Jose ',
            ...$base,
        ])->assertCreated();

        $response
            ->assertJsonPath('first_name', 'Ana Mae')
            ->assertJsonPath('last_name', 'San Jose')
            ->assertJsonPath('display_name', 'Ana Mae San Jose')
            ->assertJsonPath('name', 'Ana Mae San Jose');

        $patient = Patient::where('name', 'Ana Mae San Jose')->sole();
        $this->patchJson("/api/rnd/patients/{$patient->uuid}", ['first_name' => 'Ana Maria'])
            ->assertOk()
            ->assertJsonPath('first_name', 'Ana Maria')
            ->assertJsonPath('last_name', 'San Jose')
            ->assertJsonPath('name', 'Ana Maria San Jose');

        $activity = AuditActivity::query()
            ->where('subject_type', Patient::class)
            ->where('subject_id', $patient->id)
            ->where('event', AuditAction::Updated->value)
            ->sole();
        $this->assertEqualsCanonicalizing(
            ['first_name', 'name'],
            $activity->properties['details']['changed_fields'],
        );
        $encoded = $activity->toJson();
        $this->assertStringNotContainsString('Ana Mae', $encoded);
        $this->assertStringNotContainsString('Ana Maria', $encoded);
    }

    public function test_patient_search_is_grouped_under_status_and_covers_split_and_legacy_fields(): void
    {
        $rnd = User::factory()->rnd()->create();
        $active = Patient::factory()->create([
            'name' => 'Active Legacy',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'physician' => 'Dr Search',
            'status' => 'Active',
        ]);
        Patient::factory()->create([
            'name' => 'Search Discharged',
            'first_name' => 'Search',
            'last_name' => 'Discharged',
            'status' => 'Discharged',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/rnd/patients?status=Active&search=Search')
            ->assertOk();

        $this->assertSame([$active->uuid], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_admin_user_order_is_last_then_first_then_stable_id_with_legacy_fallback(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Zulu Admin',
            'first_name' => 'Zulu',
            'last_name' => 'Admin',
        ]);
        $second = User::factory()->rnd()->create([
            'name' => 'Ana Cruz',
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
        ]);
        $first = User::factory()->rnd()->create([
            'name' => 'Zara Adams',
            'first_name' => 'Zara',
            'last_name' => 'Adams',
        ]);

        $ids = collect($this->actingAs($admin, 'sanctum')->getJson('/api/admin/users')->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertLessThan(array_search($second->uuid, $ids, true), array_search($first->uuid, $ids, true));
    }

    public function test_nested_backend_presenters_use_display_name_with_projected_split_columns(): void
    {
        $rnd = User::factory()->rnd()->create([
            'name' => 'Stale Nested Name',
            'first_name' => 'Maria Luisa',
            'last_name' => 'De la Cruz',
        ]);
        Announcement::create([
            'user_id' => $rnd->id,
            'title' => 'Name contract',
            'body' => 'Safe content',
            'category' => 'General',
            'visibility' => 'All',
        ]);
        Sop::create(['title' => 'Procedure', 'body' => 'Safe', 'created_by' => $rnd->id]);
        Report::create([
            'user_id' => $rnd->id,
            'title' => 'Report',
            'type' => 'accomplishment_report',
            'status' => 'archived',
        ]);
        Budget::create([
            'fiscal_year' => 2035,
            'allocated_amount' => 1000,
            'created_by' => $rnd->id,
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/rnd/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.author.name', 'Maria Luisa De la Cruz');
        $this->getJson('/api/sop')
            ->assertOk()
            ->assertJsonPath('data.author.name', 'Maria Luisa De la Cruz');
        $this->getJson('/api/rnd/reports')
            ->assertOk()
            ->assertJsonPath('data.0.created_by.name', 'Maria Luisa De la Cruz');
        $this->getJson('/api/fss/budgets')
            ->assertOk()
            ->assertJsonPath('data.0.creator.name', 'Maria Luisa De la Cruz');
    }

    public function test_future_actor_snapshot_uses_display_name_and_resources_add_no_queries(): void
    {
        $actor = User::factory()->admin()->create([
            'name' => 'Stale Legacy Actor',
            'first_name' => 'Maria Luisa',
            'last_name' => 'De la Cruz',
        ]);
        $subject = User::factory()->rnd()->create();

        $this->actingAs($actor, 'sanctum');
        app(AuditLogger::class)->record(
            AuditAction::Updated,
            AuditCategory::Security,
            AuditDomain::Accounts,
            subject: $subject,
            details: ['changed_fields' => ['role']],
        );

        $this->assertSame('Maria Luisa De la Cruz', AuditActivity::query()->latest('id')->firstOrFail()->properties['actor']['name']);

        $patient = Patient::factory()->create([
            'name' => 'Stale Legacy Patient',
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz III',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $userData = (new UserResource($actor))->resolve();
        $patientData = (new PatientResource($patient))->resolve();

        $this->assertSame('Maria Luisa De la Cruz', $userData['display_name']);
        $this->assertSame('Juan Miguel Dela Cruz III', $patientData['display_name']);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_clinical_audit_logger_never_accepts_patient_name_change_values(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();

        $this->actingAs($rnd, 'sanctum');
        app(AuditLogger::class)->record(
            AuditAction::Updated,
            AuditCategory::Clinical,
            AuditDomain::Patients,
            subject: $patient,
            details: ['changed_fields' => ['first_name', 'last_name']],
            oldValues: ['first_name' => 'OLD-PATIENT-NAME-SENTINEL'],
            newValues: ['first_name' => 'NEW-PATIENT-NAME-SENTINEL'],
        );

        $encoded = AuditActivity::query()->where('event', AuditAction::Updated->value)->sole()->toJson();
        $this->assertStringNotContainsString('OLD-PATIENT-NAME-SENTINEL', $encoded);
        $this->assertStringNotContainsString('NEW-PATIENT-NAME-SENTINEL', $encoded);
    }
}
