<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
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
        config()->set('audit.features.ip_blocking', false);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?category=security');

        $response->assertOk()
            ->assertJsonCount(3, 'meta.filters.categories')
            ->assertJsonPath('meta.filters.categories.0.value', 'security')
            ->assertJsonPath('meta.filters.categories.0.label', 'Security')
            ->assertJsonPath('meta.filters.domains.0.value', 'accounts')
            ->assertJsonPath('meta.filters.actions.0.value', 'created')
            ->assertJsonPath('meta.filters.outcomes.0.value', 'success')
            ->assertJsonPath('meta.filters.severities.0.value', 'info')
            ->assertJsonPath('meta.filters.category_actions.security.0', 'created')
            ->assertJsonPath('meta.capabilities.export', false)
            ->assertJsonPath('meta.capabilities.temporary_ip_block', false);
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
        Activity::query()->delete();

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
        Activity::query()->delete();
        $patient = Patient::factory()->create();
        AuditActivity::query()->delete();
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
            ->assertJsonPath('data.0.summary', 'Updated patient')
            ->assertJsonPath('data.0.subject.id', $patient->uuid);
        $this->assertNotSame((string) $activity->id, $response->json('data.0.id'));
        $payload = $response->getContent();

        $leaks = collect([
            'clinical raw value' => 'CLINICAL-VALUE-SENTINEL',
            'arbitrary property key' => 'arbitrary_payload',
            'arbitrary property value' => 'ARBITRARY-PROPERTY-SENTINEL',
        ])->filter(fn (string $needle) => str_contains($payload, $needle))->keys()->all();

        $this->assertSame([], $leaks, 'Admin audit response leaked forbidden clinical or arbitrary properties.');
    }
}
