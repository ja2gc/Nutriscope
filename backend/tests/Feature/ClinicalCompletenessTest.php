<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicalCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private ClinicalCompletenessService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ClinicalCompletenessService::class);
    }

    private function ncp(): NcpRecord
    {
        $rnd = User::forceCreate(['name' => 'R', 'email' => 'r'.uniqid().'@x.com', 'password' => 'x', 'role' => 'RND', 'is_active' => true]);
        $patient = Patient::forceCreate(['name' => 'P', 'dob' => '1990-01-01', 'sex' => 'Male', 'admission_date' => now()->toDateString()]);

        return NcpRecord::forceCreate(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id, 'type' => 'new', 'status' => 'draft']);
    }

    public function test_assessment_incomplete_without_weight_height(): void
    {
        $ncp = $this->ncp();
        $this->assertFalse($this->svc->assessmentComplete($ncp));

        Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 70, 'height' => 170]);
        $this->assertTrue($this->svc->assessmentComplete($ncp->fresh()));
    }

    public function test_diagnosis_with_placeholder_parts_is_incomplete(): void
    {
        $ncp = $this->ncp();
        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NI',
            'problem' => '[Select problem]', 'etiology' => 'x', 'signs_symptoms' => 'y', 'pes_statement' => 'p',
        ]);
        $this->assertFalse($this->svc->diagnosisComplete($ncp->fresh()));

        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NI',
            'problem' => 'Inadequate intake', 'etiology' => 'poor appetite', 'signs_symptoms' => 'weight loss', 'pes_statement' => 'p',
        ]);
        $this->assertTrue($this->svc->diagnosisComplete($ncp->fresh()));
    }

    public function test_intervention_needs_goal_and_full_macro_prescription(): void
    {
        $ncp = $this->ncp();
        Intervention::forceCreate(['ncp_record_id' => $ncp->id]);
        $this->assertFalse($this->svc->interventionComplete($ncp->fresh()));

        $ncp->intervention->update([
            'goal_type' => 'renal_diet', 'energy_kcal' => 1800, 'protein_g' => 70, 'carbs_g' => 250, 'fat_g' => 55,
        ]);
        $this->assertTrue($this->svc->interventionComplete($ncp->fresh()));
    }

    public function test_monitoring_needs_a_meaningful_entry(): void
    {
        $ncp = $this->ncp();
        Monitoring::forceCreate(['ncp_record_id' => $ncp->id]);
        $this->assertFalse($this->svc->monitoringComplete($ncp->fresh()));

        Monitoring::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 68.0]);
        $this->assertTrue($this->svc->monitoringComplete($ncp->fresh()));
    }

    public function test_initial_adi_vs_full_adime(): void
    {
        $ncp = $this->ncp();
        Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 70, 'height' => 170]);
        Diagnosis::forceCreate(['ncp_record_id' => $ncp->id, 'domain' => 'NI', 'problem' => 'Inadequate intake', 'etiology' => 'poor appetite', 'signs_symptoms' => 'weight loss', 'pes_statement' => 'p']);
        Intervention::forceCreate(['ncp_record_id' => $ncp->id, 'goal_type' => 'renal_diet', 'energy_kcal' => 1800, 'protein_g' => 70, 'carbs_g' => 250, 'fat_g' => 55]);

        $ncp = $ncp->fresh();
        $this->assertTrue($this->svc->initialAdiComplete($ncp));
        $this->assertFalse($this->svc->fullAdimeComplete($ncp));

        Monitoring::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 68.0]);
        $this->assertTrue($this->svc->fullAdimeComplete($ncp->fresh()));
    }
}
