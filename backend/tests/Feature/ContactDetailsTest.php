<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\Patient;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function contacts(): array
    {
        return [['id' => (string) Str::uuid(), 'method' => 'email', 'label' => null, 'value' => 'demo-contact@example.test']];
    }

    private function subjects(): array
    {
        return [
            [Patient::factory()->create(['contact' => '09171234567']), '/api/rnd/patients/'],
            [Supplier::factory()->create(['contact' => 'Legacy vendor contact']), '/api/fss/suppliers/'],
        ];
    }

    public function test_both_profiles_save_and_reload_additional_contacts_and_one_preference(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'RND']), 'sanctum');
        foreach ($this->subjects() as [$record, $path]) {
            $contacts = $this->contacts();
            $this->patchJson($path.$record->uuid, ['additional_contacts' => $contacts, 'preferred_contact_id' => $contacts[0]['id']])->assertOk();
            $response = $this->getJson($path.$record->uuid)->assertOk();
            $data = $response->json('data') ?? $response->json();
            $this->assertEquals($contacts, $data['additional_contacts']);
            $this->assertSame($contacts[0]['id'], $data['preferred_contact_id']);
            $this->assertSame($record->contact, $data['contact']);
            $this->patchJson($path.$record->uuid, ['address' => 'Updated demo address'])->assertOk();
            $this->assertEquals($contacts, $record->fresh()->additional_contacts);
        }
    }

    public function test_contact_limits_formats_and_preference_are_validated_for_both_profiles(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'RND']), 'sanctum');
        foreach ($this->subjects() as [$record, $path]) {
            $entry = $this->contacts()[0];
            $invalid = [
                [['additional_contacts' => array_fill(0, 6, $entry)], 'additional_contacts'],
                [['additional_contacts' => [$entry, $entry]], 'additional_contacts.0.id'],
                [['additional_contacts' => ['not-a-list' => $entry]], 'additional_contacts'],
                [['additional_contacts' => [array_replace($entry, ['value' => 'not-an-email'])]], 'additional_contacts.0.value'],
                [['additional_contacts' => [array_replace($entry, ['method' => 'phone', 'value' => 'letters only'])]], 'additional_contacts.0.value'],
                [['additional_contacts' => [array_replace($entry, ['method' => 'other', 'label' => ''])]], 'additional_contacts.0.label'],
                [['additional_contacts' => [array_replace($entry, ['method' => 'other', 'label' => str_repeat('x', 51)])]], 'additional_contacts.0.label'],
                [['additional_contacts' => [array_replace($entry, ['value' => str_repeat('x', 256)])]], 'additional_contacts.0.value'],
                [['preferred_contact_id' => (string) Str::uuid()], 'preferred_contact_id'],
                [['contact' => null, 'preferred_contact_id' => 'primary'], 'preferred_contact_id'],
            ];
            foreach ($invalid as [$payload, $field]) {
                $this->patchJson($path.$record->uuid, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
            }
            $this->assertNull($record->fresh()->preferred_contact_id);
        }
    }

    public function test_five_contacts_and_custom_methods_are_allowed_but_a_removed_preference_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'RND']), 'sanctum');
        foreach ($this->subjects() as [$record, $path]) {
            $contacts = array_map(fn (int $index) => ['id' => (string) Str::uuid(), 'method' => 'other', 'label' => 'Viber '.$index, 'value' => 'Demo handle '.$index], range(1, 5));
            $this->patchJson($path.$record->uuid, ['additional_contacts' => $contacts, 'preferred_contact_id' => $contacts[0]['id']])->assertOk();
            $this->patchJson($path.$record->uuid, ['additional_contacts' => array_slice($contacts, 1)])->assertUnprocessable()->assertJsonValidationErrors('preferred_contact_id');
            $this->patchJson($path.$record->uuid, ['additional_contacts' => [], 'preferred_contact_id' => null])->assertOk();
            $this->assertSame([], $record->fresh()->additional_contacts);
            $this->assertNull($record->fresh()->preferred_contact_id);
        }
    }

    public function test_patient_contact_values_are_not_copied_into_audit_values(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'RND']), 'sanctum');
        $patient = Patient::factory()->create();
        $this->patchJson('/api/rnd/patients/'.$patient->uuid, ['additional_contacts' => $this->contacts()])->assertOk();
        $this->assertSame('demo-contact@example.test', $patient->fresh()->additional_contacts[0]['value']);
        $this->assertStringNotContainsString('demo-contact@example.test', AuditActivity::all()->toJson());
    }

    public function test_create_requests_accept_contacts_and_fss_cannot_author_them(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $contacts = $this->contacts();
        $payload = ['contact' => '09171234567', 'additional_contacts' => $contacts, 'preferred_contact_id' => $contacts[0]['id']];
        $patient = $this->actingAs($rnd, 'sanctum')->postJson('/api/rnd/patients', $payload + ['first_name' => 'Demo', 'last_name' => 'Patient', 'dob' => '1990-01-01', 'sex' => 'Female', 'admission_date' => '2026-10-20'])->assertCreated();
        $this->assertEquals($contacts, $patient->json('additional_contacts'));
        $supplier = $this->postJson('/api/fss/suppliers', $payload + ['name' => 'Demo contact vendor'])->assertCreated();
        $this->assertEquals($contacts, $supplier->json('data.additional_contacts'));
        $this->actingAs(User::factory()->create(['role' => 'FSS']), 'sanctum');
        $this->patchJson('/api/rnd/patients/'.$patient->json('id'), $payload)->assertForbidden();
        $this->patchJson('/api/fss/suppliers/'.$supplier->json('data.id'), $payload)->assertForbidden();
    }
}
