<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\NcpAppointment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NcpAppointmentBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_follow_up_date_creates_one_unbound_scheduled_appointment_idempotently(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'active',
        ]);
        $intervention = Intervention::factory()->create([
            'ncp_record_id' => $ncp->id,
            'next_followup_date' => '2026-10-08',
        ]);

        $migration = require database_path('migrations/2026_09_07_105542_backfill_ncp_appointments_from_followup_dates.php');
        $migration->up();
        $migration->up();

        $appointment = NcpAppointment::query()->sole();
        $this->assertSame($patient->id, $appointment->patient_id);
        $this->assertSame($rnd->id, $appointment->rnd_user_id);
        $this->assertNull($appointment->ncp_record_id);
        $this->assertSame('scheduled', $appointment->source);
        $this->assertSame('scheduled', $appointment->status);
        $this->assertSame('Nutrition follow-up', $appointment->purpose);
        $this->assertSame('2026-10-08 09:00:00', $appointment->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-08', $intervention->fresh()->next_followup_date->format('Y-m-d'));
    }
}
