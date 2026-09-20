<?php

namespace App\Services\Audit;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Model;

class AuditPatientSnapshot
{
    public function resolve(?Model $subject, ?int $patientId): ?string
    {
        if ($patientId === null || $patientId < 1) {
            return null;
        }

        $patient = $subject instanceof Patient && (int) $subject->getKey() === $patientId
            ? $subject
            : Patient::query()->whereKey($patientId)->first(['id', 'patient_code']);
        $code = trim((string) $patient?->patient_code);

        return $code === '' ? null : $code;
    }
}
