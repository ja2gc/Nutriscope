<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Models\AuditActivity;
use App\Models\AuditSetting;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\AuditFilterMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'Admin',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_admin_can_filter_paginated_audit_logs(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $fss = User::factory()->create(['role' => 'FSS']);
        $patient = Patient::factory()->create();

        Activity::create([
            'log_name' => 'audit',
            'event' => 'created',
            'description' => 'Created patient',
            'causer_type' => User::class,
            'causer_id' => $rnd->id,
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'subject_public_id' => $patient->uuid,
            'category' => 'clinical',
            'domain' => 'patients',
            'severity' => 'info',
            'outcome' => 'success',
            'properties' => [
                'actor' => ['kind' => 'user', 'public_id' => $rnd->uuid, 'name' => $rnd->name, 'role' => 'RND'],
                'details' => ['public_id' => $patient->uuid],
            ],
            'created_at' => '2026-06-10 08:00:00',
        ]);
        Activity::create([
            'log_name' => 'audit',
            'event' => 'updated',
            'description' => 'Updated inventory',
            'causer_type' => User::class,
            'causer_id' => $fss->id,
            'subject_type' => 'inventory',
            'subject_id' => 202,
            'created_at' => '2026-06-11 08:00:00',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/audit-logs?actor_id={$rnd->uuid}&subject_id={$patient->uuid}&action=created&start=2026-06-10&end=2026-06-10&per_page=5");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'created')
            ->assertJsonPath('data.0.actor.id', $rnd->uuid)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'category', 'domain', 'action', 'action_label', 'summary', 'severity',
                    'outcome', 'actor', 'subject', 'context', 'occurred_at', 'details', 'changes',
                ]],
                'links',
                'meta',
            ]);
    }

    public function test_admin_audit_response_exposes_one_backend_taxonomy_and_disabled_capabilities(): void
    {
        config()->set('audit.features.export', false);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs');

        $response->assertOk()
            ->assertJsonCount(4, 'meta.filters.modules')
            ->assertJsonPath('meta.filters.modules.0.value', 'security_administration')
            ->assertJsonPath('meta.filters.actions.0.value', 'created')
            ->assertJsonPath('meta.filters.outcomes.0.value', 'success')
            ->assertJsonPath('meta.filters.severities.0.value', 'info');
        $this->assertArrayNotHasKey('categories', $response->json('meta.filters'));
        $this->assertArrayNotHasKey('domains', $response->json('meta.filters'));
        $this->assertArrayNotHasKey('category_actions', $response->json('meta.filters'));
        $this->assertArrayNotHasKey('export', $response->json('meta.capabilities'));
    }

    public function test_admin_can_read_static_retention_periods_and_config_fallback_state(): void
    {
        config(['audit.features.retention' => false]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-retention');

        $response->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.source', 'config')
            ->assertJsonPath('data.periods.security', 365)
            ->assertJsonPath('data.periods.clinical', 2190)
            ->assertJsonPath('data.periods.operations', 1095)
            ->assertJsonPath('data.periods.legacy', 90);
    }

    public function test_database_retention_state_overrides_config_and_is_exposed_in_audit_metadata(): void
    {
        config(['audit.features.retention' => false]);
        AuditSetting::query()->create([
            'key' => AuditSetting::RETENTION_ENABLED,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs');

        $response->assertOk()
            ->assertJsonPath('meta.retention.enabled', true)
            ->assertJsonPath('meta.retention.source', 'database')
            ->assertJsonPath('meta.retention.periods.clinical', 2190);
    }

    public function test_only_admin_can_read_or_change_retention_state(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $this->getJson('/api/admin/audit-retention')->assertUnauthorized();
        $this->putJson('/api/admin/audit-retention', ['enabled' => true])->assertUnauthorized();

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/admin/audit-retention')
            ->assertForbidden();
        $this->actingAs($rnd, 'sanctum')
            ->putJson('/api/admin/audit-retention', ['enabled' => true])
            ->assertForbidden();
    }

    public function test_retention_update_requires_an_explicit_boolean(): void
    {
        foreach ([[], ['enabled' => 1], ['enabled' => 'true'], ['enabled' => null]] as $payload) {
            $this->actingAs($this->admin, 'sanctum')
                ->putJson('/api/admin/audit-retention', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('enabled');
        }
    }

    public function test_admin_retention_change_is_persisted_and_audited_with_safe_old_and_new_values(): void
    {
        $this->travelTo('2026-07-14 12:34:56');
        config(['audit.features.retention' => false]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/audit-retention', ['enabled' => true]);

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.source', 'database');
        $this->assertDatabaseHas('audit_settings', [
            'key' => AuditSetting::RETENTION_ENABLED,
            'enabled' => true,
        ]);

        $event = AuditActivity::query()
            ->where('event', 'settings_changed')
            ->where('domain', 'system')
            ->sole();
        $this->assertSame($this->admin->id, $event->causer_id);
        $this->assertSame('2026-07-14 12:34:56', $event->created_at->format('Y-m-d H:i:s'));
        $this->assertSame(['retention_enabled' => false], $event->properties->get('old'));
        $this->assertSame(['retention_enabled' => true], $event->properties->get('attributes'));
        $this->assertSame(['retention_enabled'], $event->properties->get('details')['changed_fields']);
        $this->assertStringNotContainsString('patient', strtolower($event->properties->toJson()));
    }

    public function test_clinical_audit_values_are_redacted_before_admin_api_exposes_them(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $this->actingAs($rnd, 'sanctum');
        $patient = Patient::factory()->create([
            'name' => 'Jane Sensitive',
            'contact' => '09171234567',
            'medical_diagnosis' => 'Private diagnosis',
        ]);

        $patient->update([
            'contact' => '09998887777',
            'medical_diagnosis' => 'Updated private diagnosis',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?subject_id='.$patient->uuid);

        $response->assertOk();

        $payload = json_encode($response->json('data'), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('Jane Sensitive', $payload);
        $this->assertStringNotContainsString('09171234567', $payload);
        $this->assertStringNotContainsString('09998887777', $payload);
        $this->assertStringNotContainsString('Private diagnosis', $payload);
        $this->assertStringNotContainsString('Updated private diagnosis', $payload);
        $this->assertStringContainsString('redacted', $payload);
    }

    public function test_admin_audit_logs_preserve_offset_pagination_metadata(): void
    {
        AuditFixture::delete(Activity::query());

        foreach (range(1, 3) as $index) {
            Activity::create([
                'log_name' => 'audit',
                'event' => 'updated',
                'description' => "Audit event {$index}",
                'subject_type' => Patient::class,
                'subject_id' => $index,
                'created_at' => "2026-06-10 08:0{$index}:00",
            ]);
        }

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?per_page=2&page=2');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.from', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.to', 3)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
            ]);
    }

    public function test_admin_response_never_exposes_clinical_values_or_arbitrary_properties(): void
    {
        AuditFixture::delete(Activity::query());
        $patient = Patient::factory()->create();
        AuditFixture::delete(AuditActivity::query());
        $activity = Activity::create([
            'log_name' => 'audit',
            'event' => 'updated',
            'description' => 'Updated patient',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'subject_public_id' => $patient->uuid,
            'category' => 'clinical',
            'domain' => 'patients',
            'properties' => [
                'details' => [
                    'public_id' => $patient->uuid,
                    'changed_fields' => ['medical_diagnosis'],
                    'medical_diagnosis' => 'CLINICAL-VALUE-SENTINEL',
                ],
                'arbitrary_payload' => 'ARBITRARY-PROPERTY-SENTINEL',
            ],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?subject_id='.$patient->uuid);

        $response->assertOk()
            ->assertJsonPath('data.0.action', 'updated')
            ->assertJsonPath('data.0.summary', 'Unknown actor changed Medical Diagnosis for patient; values hidden.')
            ->assertJsonPath('data.0.subject.id', null);
        $this->assertNotSame((string) $activity->id, $response->json('data.0.id'));
        $payload = $response->getContent();

        $leaks = collect([
            'clinical raw value' => 'CLINICAL-VALUE-SENTINEL',
            'arbitrary property key' => 'arbitrary_payload',
            'arbitrary property value' => 'ARBITRARY-PROPERTY-SENTINEL',
        ])->filter(fn (string $needle) => str_contains($payload, $needle))->keys()->all();

        $this->assertSame([], $leaks, 'Admin audit response leaked forbidden clinical or arbitrary properties.');
    }

    public function test_admin_can_filter_by_module_and_its_contextual_subfilter(): void
    {
        AuditFixture::delete(AuditActivity::query());

        $events = [
            ['food-library', AuditModule::NutritionCare, AuditDomain::NutritionLibrary, AuditAction::Imported, null, []],
            ['patient-ncp', AuditModule::NutritionCare, AuditDomain::Patients, AuditAction::Updated, Patient::class, []],
            ['catalog', AuditModule::FoodServiceOperations, AuditDomain::FoodService, AuditAction::Updated, FsItem::class, []],
            ['menus', AuditModule::FoodServiceOperations, AuditDomain::FoodService, AuditAction::Updated, MenuCycle::class, []],
            ['procurement', AuditModule::FoodServiceOperations, AuditDomain::Procurement, AuditAction::Ordered, null, []],
            ['budget', AuditModule::FoodServiceOperations, AuditDomain::Budget, AuditAction::Adjusted, null, []],
            ['authentication', AuditModule::SecurityAdministration, AuditDomain::Accounts, AuditAction::LoginSucceeded, null, []],
            ['accounts', AuditModule::SecurityAdministration, AuditDomain::Accounts, AuditAction::AccountBlocked, User::class, []],
            ['oversight', AuditModule::SecurityAdministration, AuditDomain::System, AuditAction::AuditLogViewed, null, []],
            ['settings', AuditModule::SecurityAdministration, AuditDomain::System, AuditAction::SettingsChanged, null, []],
            ['report', AuditModule::Reports, AuditDomain::Reports, AuditAction::Generated, null, ['details' => ['report_type' => 'menu_calendar']]],
        ];

        foreach ($events as [$description, $module, $domain, $action, $subjectType, $properties]) {
            AuditActivity::query()->create([
                'log_name' => 'audit',
                'description' => $description,
                'event' => $action,
                'category' => $module === AuditModule::SecurityAdministration
                    ? AuditCategory::Security
                    : AuditCategory::Operations,
                'domain' => $domain,
                'module' => $module,
                'subject_type' => $subjectType,
                'properties' => $properties,
            ]);
        }
        Cache::put('audit-list-view:'.$this->admin->getAuthIdentifier(), true, 900);

        foreach ([
            ['nutrition_care', 'food_library', 'food-library'],
            ['nutrition_care', 'patients_ncp', 'patient-ncp'],
            ['food_service_operations', 'catalog', 'catalog'],
            ['food_service_operations', 'menus', 'menus'],
            ['food_service_operations', 'procurement', 'procurement'],
            ['food_service_operations', 'budget', 'budget'],
            ['security_administration', 'authentication', 'authentication'],
            ['security_administration', 'accounts', 'accounts'],
            ['security_administration', 'audit_oversight', 'oversight'],
            ['security_administration', 'settings', 'settings'],
            ['reports', 'menu_calendar', 'report'],
        ] as [$module, $subfilter, $description]) {
            $response = $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/admin/audit-logs?module={$module}&subfilter={$subfilter}");

            $response->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.module', $module)
                ->assertJsonPath('data.0.summary', fn (string $summary): bool => $summary !== '');
            $this->assertSame($description, AuditActivity::query()
                ->where('public_id', $response->json('data.0.id'))->value('description'));
        }

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?module=nutrition_care&subfilter=budget')
            ->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?module=nope')
            ->assertUnprocessable();
    }

    public function test_module_metadata_has_exact_tabs_contextual_filters_actions_and_one_count_aggregate(): void
    {
        AuditFixture::delete(AuditActivity::query());
        foreach (AuditModule::cases() as $module) {
            AuditActivity::query()->create([
                'log_name' => 'audit',
                'description' => $module->value,
                'event' => AuditAction::Created,
                'category' => AuditCategory::Operations,
                'domain' => AuditDomain::System,
                'module' => $module,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $metadata = app(AuditFilterMetadata::class)->for($this->admin);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame([
            'security_administration', 'nutrition_care', 'food_service_operations', 'reports',
        ], collect($metadata['filters']['modules'])->pluck('value')->all());
        $this->assertSame([
            'authentication', 'accounts', 'audit_oversight', 'settings',
        ], collect($metadata['filters']['module_subfilters']['security_administration'])->pluck('value')->all());
        $this->assertSame(['food_library', 'patients_ncp'], collect($metadata['filters']['module_subfilters']['nutrition_care'])->pluck('value')->all());
        $this->assertSame(['catalog', 'menus', 'procurement', 'budget'], collect($metadata['filters']['module_subfilters']['food_service_operations'])->pluck('value')->all());
        $this->assertSame([
            'program_project_activity', 'menu_calendar', 'procurement_pack', 'demographic_census',
            'patient_menu_plan', 'ncp_summary', 'accomplishment_report',
        ], collect($metadata['filters']['module_subfilters']['reports'])->pluck('value')->all());
        $this->assertContains('login_succeeded', $metadata['filters']['module_actions']['security_administration']);
        $this->assertContains('imported', $metadata['filters']['module_actions']['nutrition_care']);
        $this->assertContains('received', $metadata['filters']['module_actions']['food_service_operations']);
        $this->assertContains('generated', $metadata['filters']['module_actions']['reports']);
        $this->assertSame([
            'all' => 4,
            'security_administration' => 1,
            'nutrition_care' => 1,
            'food_service_operations' => 1,
            'reports' => 1,
        ], $metadata['filters']['module_counts']);

        $countQueries = $queries->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'from `activity_log`')
            && str_contains(strtolower($query['query']), 'security_administration'));
        $this->assertCount(1, $countQueries, 'Module counts must use one conditional aggregate query.');
    }

    public function test_actor_lookup_is_admin_only_paginated_and_searches_names_without_patient_or_email_data(): void
    {
        $first = User::factory()->create([
            'first_name' => 'Maria Luisa',
            'last_name' => 'Dela Cruz',
            'name' => 'Maria Luisa Dela Cruz',
            'email' => 'private-actor@example.test',
        ]);
        $second = User::factory()->create([
            'first_name' => 'Jose Miguel',
            'last_name' => 'Santos',
            'name' => 'Jose Miguel Santos',
        ]);
        User::factory()->create(['first_name' => 'No', 'last_name' => 'Events', 'name' => 'No Events']);
        $patient = Patient::factory()->create(['first_name' => 'Patient', 'last_name' => 'Sentinel', 'name' => 'Patient Sentinel']);
        AuditActivity::query()->create([
            'log_name' => 'audit',
            'description' => 'patient linked',
            'event' => AuditAction::Updated,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Patients,
            'module' => AuditModule::NutritionCare,
            'causer_type' => $first->getMorphClass(),
            'causer_id' => $first->id,
            'patient_display_name_snapshot' => $patient->display_name,
        ]);
        $second->delete();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-actors?search=Maria%20Luisa%20Dela&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->uuid)
            ->assertJsonPath('data.0.name', 'Maria Luisa Dela Cruz')
            ->assertJsonMissing(['email' => 'private-actor@example.test'])
            ->assertJsonPath('meta.per_page', 1);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-actors?search=Patient%20Sentinel')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-actors?search=private-actor')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/audit-actors?selected_id={$second->uuid}")
            ->assertOk()->assertJsonPath('data.0.name', 'Jose Miguel Santos');

        $this->actingAs(User::factory()->rnd()->create(), 'sanctum')
            ->getJson('/api/admin/audit-actors')->assertForbidden();
        auth()->forgetGuards();
        $this->getJson('/api/admin/audit-actors')->assertUnauthorized();
    }
}
