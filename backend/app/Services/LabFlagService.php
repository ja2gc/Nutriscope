<?php

namespace App\Services;

/**
 * Single source of truth for biochemical reference ranges and LOW/HIGH flagging.
 *
 * Shared by AiDiagnosisController (abnormal-lab evidence for AI diagnosis) and
 * MonitoringPlanService (which labs a patient tracks + their reference bands),
 * so the two never drift. Sex-aware where clinically established
 * (hemoglobin, hematocrit, creatinine, hdl).
 */
class LabFlagService
{
    /**
     * Sex-aware reference ranges. null bound = unbounded on that side.
     *
     * @return array<string,array{low:?float,high:?float}>
     */
    public function ranges(?string $sex = null): array
    {
        $male = strtoupper(substr(trim((string) $sex), 0, 1)) === 'M';

        return [
            'albumin'       => ['low' => 3.5,  'high' => 5.5],
            'hemoglobin'    => $male ? ['low' => 13.5, 'high' => 17.5] : ['low' => 12.0, 'high' => 15.5],
            'hematocrit'    => $male ? ['low' => 41.0, 'high' => 53.0] : ['low' => 36.0, 'high' => 46.0],
            'glucose'       => ['low' => 70.0,  'high' => 99.0],
            'hba1c'         => ['low' => null,  'high' => 5.6],
            'bun'           => ['low' => 7.0,   'high' => 18.0],
            'creatinine'    => $male ? ['low' => 0.7,  'high' => 1.2]  : ['low' => 0.5,  'high' => 0.9],
            'sodium'        => ['low' => 136.0, 'high' => 145.0],
            'potassium'     => ['low' => 3.5,   'high' => 5.1],
            'calcium'       => ['low' => 8.7,   'high' => 10.3],
            'phosphate'     => ['low' => 2.5,   'high' => 4.5],
            'cholesterol'   => ['low' => null,  'high' => 200.0],
            'ldl'           => ['low' => null,  'high' => 100.0],
            'hdl'           => $male ? ['low' => 40.0, 'high' => null] : ['low' => 50.0, 'high' => null],
            'triglycerides' => ['low' => null,  'high' => 150.0],
        ];
    }

    /**
     * Return only the out-of-range labs with their flag and actual value.
     *
     * @param  array<string,mixed> $labs
     * @return array<string,array{value:float,status:string}>
     */
    public function flag(array $labs, ?string $sex = null): array
    {
        $flagged = [];

        foreach ($this->ranges($sex) as $key => $range) {
            $raw = $labs[$key] ?? null;
            if ($raw === null || ! is_numeric($raw)) {
                continue;
            }

            $value  = (float) $raw;
            $status = null;
            if ($range['low'] !== null && $value < $range['low']) {
                $status = 'LOW';
            }
            if ($range['high'] !== null && $value > $range['high']) {
                $status = 'HIGH';
            }

            if ($status !== null) {
                $flagged[$key] = ['value' => $value, 'status' => $status];
            }
        }

        return $flagged;
    }
}
