<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Support\ClinicalAiContext;
use Tests\TestCase;

class ClinicalAiContextTest extends TestCase
{
    public function test_referral_and_medications_keep_clinical_information_without_patient_names(): void
    {
        $patient = new Patient(['name' => 'Fictional Mira Santos', 'first_name' => 'Mira', 'last_name' => 'Santos', 'medical_diagnosis' => 'Mira Santos: type 2 diabetes and CKD']);
        $assessment = new Assessment(['primary_diagnosis_category' => 'Renal', 'medications' => ['Mira takes insulin and furosemide']]);
        $record = new NcpRecord;
        $record->setRelation('patient', $patient);
        $record->setRelation('assessment', $assessment);
        $context = ClinicalAiContext::patientEvidence($record);

        $this->assertStringContainsString('type 2 diabetes', $context['referral_diagnosis']);
        $this->assertStringContainsString('insulin', $context['medications'][0]);
        $this->assertSame('Renal', $context['nutrition_care_category']);
        $this->assertStringNotContainsString('Mira', json_encode($context));
        $this->assertStringNotContainsString('Santos', json_encode($context));
        $this->assertArrayNotHasKey('name', $context);
    }

    public function test_only_matching_clinical_cards_are_sent_without_source_metadata(): void
    {
        $context = ClinicalAiContext::guidance([
            'referral_diagnosis' => 'CKD stage 4',
            'laboratory_flags' => ['potassium' => 'high'],
        ], 'diagnosis');

        $this->assertStringContainsString('potassium', $context);
        $this->assertStringContainsString('intake', $context);
        $this->assertStringNotContainsString('HbA1c', $context);
        $this->assertStringNotContainsString('cirrhosis', $context);
        $this->assertStringNotContainsString('https://', $context);
        $this->assertLessThan(600, strlen($context));
    }

    public function test_obvious_behavior_and_unrelated_or_negated_disease_need_no_extra_card(): void
    {
        $this->assertSame('', ClinicalAiContext::guidance(['social_history' => 'Cannot afford food; skips meals'], 'diagnosis'));
        $this->assertSame('', ClinicalAiContext::guidance(['referral_diagnosis' => 'No diabetes. Family history of CKD.', 'laboratory_flags' => ['potassium' => 'high']], 'diagnosis'));
        $this->assertSame('', ClinicalAiContext::guidance(['referral_diagnosis' => 'CKD', 'laboratory_flags' => ['hba1c' => 'high']], 'diagnosis'));
        $this->assertSame('', ClinicalAiContext::guidance(['referral_diagnosis' => 'Adrenal disorder', 'laboratory_flags' => ['potassium' => 'high']], 'diagnosis'));
    }

    public function test_monitoring_uses_goal_and_present_indicators_without_diagnostic_instructions(): void
    {
        $summary = ['goal' => 'cardiac_diet', 'goal_type_detail' => 'heart_failure', 'indicators' => [['key' => 'weight', 'current' => 60, 'previous' => 65]]];
        $context = ClinicalAiContext::guidance($summary, 'monitoring');
        $this->assertStringContainsString('fluid', $context);
        $this->assertStringNotContainsString('diagnose', $context);
        $this->assertStringNotContainsString('PES', $context);
        $this->assertStringNotContainsString('potassium', $context);
    }

    public function test_monitoring_recall_and_symptom_fields_can_select_relevant_goal_guidance(): void
    {
        $renal = ClinicalAiContext::guidance(['goal' => 'renal_diet', 'recall_intake_estimate' => ['protein_g' => 40]], 'monitoring');
        $this->assertStringContainsString('actual prescription', $renal);
        $recovery = ClinicalAiContext::guidance(['goal' => 'high_protein', 'goal_type_detail' => 'postoperative_recovery', 'symptoms' => 'Vomiting after meals'], 'monitoring');
        $this->assertStringContainsString('tolerance', $recovery);
    }

    public function test_clinical_ranges_survive_redaction_while_contacts_and_names_do_not(): void
    {
        $patient = new Patient(['first_name' => 'Synthetic', 'last_name' => 'Review', 'contact' => '09171234567']);
        $text = ClinicalAiContext::redact('Synthetic Review: 1800 - 2200 kcal/day; 10000 - 20000 IU; 09171234567; +63 917 123 4567; review@example.test', $patient);
        $this->assertStringContainsString('1800 - 2200 kcal/day', $text);
        $this->assertStringContainsString('10000 - 20000 IU', $text);
        foreach (['Synthetic', 'Review', '09171234567', '+63 917 123 4567', 'review@example.test'] as $identifier) {
            $this->assertStringNotContainsString($identifier, $text);
        }
    }

    public function test_negating_a_different_finding_does_not_remove_a_confirmed_condition(): void
    {
        $evidence = ['referral_diagnosis' => 'CKD stage 4 with no edema', 'laboratory_flags' => ['potassium' => 'high']];
        $this->assertNotSame('', ClinicalAiContext::guidance($evidence, 'diagnosis'));
        $evidence['referral_diagnosis'] = 'No CKD; family history of renal disease';
        $this->assertSame('', ClinicalAiContext::guidance($evidence, 'diagnosis'));
        foreach (['CKD ruled out', 'CKD suspected', 'Possible CKD'] as $diagnosis) {
            $evidence['referral_diagnosis'] = $diagnosis;
            $this->assertSame('', ClinicalAiContext::guidance($evidence, 'diagnosis'));
        }
    }

    public function test_potassium_guidance_does_not_assume_the_direction_of_the_abnormality(): void
    {
        $guidance = ClinicalAiContext::guidance(['referral_diagnosis' => 'CKD', 'laboratory_flags' => ['potassium' => 'low']], 'diagnosis');
        $this->assertNotSame('', $guidance);
        $this->assertStringNotContainsString('High potassium', $guidance);
        $this->assertStringContainsString('supplied ranges', $guidance);
    }
}
