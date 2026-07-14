<?php

namespace App\Services;

use App\Models\NcpRecord;

/**
 * Defines the MINIMUM clinically-meaningful content for each ADIME step, so an
 * empty row can never satisfy the workflow, drive activation, or appear in a
 * final report. Row existence alone is not completion.
 *
 * Each `*Complete()` returns a bool; each `*Missing()` returns a human-readable
 * list of what's missing (for actionable API/report messages). Pure reads — no
 * mutation.
 */
class ClinicalCompletenessService
{
    /** Placeholder tokens the builder UIs emit when a field is left unselected. */
    private const PLACEHOLDER = '[';

    // ── Assessment (A) ────────────────────────────────────────────────────────

    /** Minimum: anthropometrics needed by every downstream calculation. */
    public function assessmentComplete(NcpRecord $ncp): bool
    {
        return empty($this->assessmentMissing($ncp));
    }

    /** @return string[] */
    public function assessmentMissing(NcpRecord $ncp): array
    {
        $a = $ncp->assessment;
        if (! $a) {
            return ['Nutrition assessment'];
        }

        $missing = [];
        if (! ($a->weight > 0)) {
            $missing[] = 'Weight';
        }
        if (! ($a->height > 0)) {
            $missing[] = 'Height';
        }

        return $missing;
    }

    // ── Diagnosis / PES (D) ───────────────────────────────────────────────────

    /** Minimum: at least one diagnosis with a real P-E-S (no placeholder parts). */
    public function diagnosisComplete(NcpRecord $ncp): bool
    {
        return empty($this->diagnosisMissing($ncp));
    }

    /** @return string[] */
    public function diagnosisMissing(NcpRecord $ncp): array
    {
        $diagnoses = $ncp->diagnoses;
        if ($diagnoses->isEmpty()) {
            return ['At least one nutrition diagnosis (PES statement)'];
        }

        $hasValid = $diagnoses->contains(fn ($d) => $this->isValidPes($d));
        if (! $hasValid) {
            return ['A complete PES statement (problem, etiology, and signs/symptoms)'];
        }

        return [];
    }

    private function isValidPes($diagnosis): bool
    {
        foreach (['problem', 'etiology', 'signs_symptoms'] as $part) {
            $value = trim((string) ($diagnosis->{$part} ?? ''));
            if ($value === '' || str_contains($value, self::PLACEHOLDER)) {
                return false;
            }
        }

        return true;
    }

    // ── Intervention / Prescription (I) ───────────────────────────────────────

    /** Minimum: a goal and a real prescription (energy + the macro set). */
    public function interventionComplete(NcpRecord $ncp): bool
    {
        return empty($this->interventionMissing($ncp));
    }

    /** @return string[] */
    public function interventionMissing(NcpRecord $ncp): array
    {
        $iv = $ncp->intervention;
        if (! $iv) {
            return ['Nutrition intervention'];
        }

        $missing = [];
        if (empty($iv->goal_type)) {
            $missing[] = 'Intervention goal';
        }
        if (! ($iv->energy_kcal > 0)) {
            $missing[] = 'Energy target';
        }
        if (! ($iv->protein_g > 0)) {
            $missing[] = 'Protein target';
        }
        if (! ($iv->carbs_g > 0)) {
            $missing[] = 'Carbohydrate target';
        }
        if (! ($iv->fat_g > 0)) {
            $missing[] = 'Fat target';
        }

        return $missing;
    }

    /** IV-04: a follow-up plan must exist to justify the Monitoring step. */
    public function followUpPlanComplete(NcpRecord $ncp): bool
    {
        $iv = $ncp->intervention;

        return $iv && (! empty($iv->next_followup_date) || ! empty($iv->session_type));
    }

    // ── Monitoring / Evaluation (M/E) ─────────────────────────────────────────

    /** Minimum: at least one monitoring entry carrying a measured/observed value. */
    public function monitoringComplete(NcpRecord $ncp): bool
    {
        return empty($this->monitoringMissing($ncp));
    }

    /** @return string[] */
    public function monitoringMissing(NcpRecord $ncp): array
    {
        $entries = $ncp->monitorings;
        if ($entries->isEmpty()) {
            return ['At least one monitoring/evaluation entry'];
        }

        $hasMeaningful = $entries->contains(function ($m) {
            return $m->weight !== null
                || $m->bmi !== null
                || ! empty($m->lab_values)
                || ! empty($m->goal_achievement)
                || filled($m->intake_notes)
                || filled($m->symptoms)
                || filled($m->clinical_summary);
        });

        return $hasMeaningful ? [] : ['A monitoring entry with measured or observed data'];
    }

    // ── Aggregate ─────────────────────────────────────────────────────────────

    /** Initial ADI (Assessment + Diagnosis + Intervention) — drives activation. */
    public function initialAdiComplete(NcpRecord $ncp): bool
    {
        return $this->assessmentComplete($ncp)
            && $this->diagnosisComplete($ncp)
            && $this->interventionComplete($ncp);
    }

    /** Full ADIME — initial ADI plus meaningful Monitoring/Evaluation. */
    public function fullAdimeComplete(NcpRecord $ncp): bool
    {
        return $this->initialAdiComplete($ncp) && $this->monitoringComplete($ncp);
    }
}
