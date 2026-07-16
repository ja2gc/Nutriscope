<?php

namespace Tests\Feature;

use App\Models\Intervention;
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
        $overdue = $this->patientWithFollowUp($rnd, now()->subDays(3)->toDateString());
        $future = $this->patientWithFollowUp($rnd, now()->addDays(3)->toDateString());
        $this->patientWithFollowUp($rnd, null);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/rnd/patients?upcoming_followups=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($overdue->uuid));
        $this->assertTrue($ids->contains($future->uuid));
    }

    private function patientWithFollowUp(User $rnd, ?string $date): Patient
    {
        $patient = Patient::factory()->create();
        $record = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
        ]);
        Intervention::factory()->create([
            'ncp_record_id' => $record->id,
            'next_followup_date' => $date,
        ]);

        return $patient;
    }
}
