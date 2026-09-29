<?php

namespace App\Services;

use App\Models\NcpRecord;
use Illuminate\Support\Carbon;

class InterventionCalculationContextService
{
    /** @return array<string, mixed> */
    public function for(NcpRecord $ncpRecord): array
    {
        return $this->build($ncpRecord);
    }

    /** @return array<string, mixed> */
    public function prefillForMonitoring(NcpRecord $ncpRecord): array
    {
        return $this->build($ncpRecord);
    }

    /** @return array<string, mixed> */
    private function build(NcpRecord $ncpRecord): array
    {
        $ncpRecord->loadMissing(['assessment', 'patient']);
        $assessment = $ncpRecord->assessment;
        $monitoring = $ncpRecord->monitorings()
            ->orderByDesc('observed_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        $fields = [
            'weight',
            'height',
            'edema_present',
            'dry_weight_kg',
            'physical_activity_level',
            'pregnancy_lactation_status',
            'allergies',
            'dietary_restrictions',
            'food_dislikes',
        ];
        $context = [];

        foreach ($fields as $field) {
            $context[$field] = $monitoring?->getAttribute($field)
                ?? $assessment?->getAttribute($field);
        }

        $context['source_type'] = $monitoring === null ? 'assessment' : 'monitoring';
        $context['source_monitoring_id'] = $monitoring?->uuid;
        $context['source_monitoring_date'] = $monitoring?->observed_at?->toDateString()
            ?? $monitoring?->created_at?->toDateString();
        $context['age_years'] = $ncpRecord->patient?->dob
            ? Carbon::parse($ncpRecord->patient->dob)->age
            : null;
        $context['sex'] = $ncpRecord->patient?->sex;

        return $context;
    }
}
