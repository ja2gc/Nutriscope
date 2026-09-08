<?php

namespace Tests\Feature;

use App\Models\NcpAppointment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientFollowUpPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_filter_includes_overdue_and_future_dates(): void
    {
        $rnd = User::factory()->rnd()->create();
        $overdue = $this->patientWithFollowUp($rnd, now()->subDays(3)->toDateTimeString());
        $future = $this->patientWithFollowUp($rnd, now()->addDays(3)->toDateTimeString());
        $this->patientWithFollowUp($rnd, null);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/rnd/patients?upcoming_followups=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($overdue->uuid));
        $this->assertTrue($ids->contains($future->uuid));
        $this->assertNotNull(collect($response->json('data'))->firstWhere('id', $future->uuid)['next_appointment_at']);
    }

    private function patientWithFollowUp(User $rnd, ?string $date): Patient
    {
        $patient = Patient::factory()->create();
        NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
        ]);
        if ($date !== null) {
            NcpAppointment::factory()->create([
                'patient_id' => $patient->id,
                'ncp_record_id' => null,
                'rnd_user_id' => $rnd->id,
                'status' => 'scheduled',
                'scheduled_at' => $date,
            ]);
        }

        return $patient;
    }
}
