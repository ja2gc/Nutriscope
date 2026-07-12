<?php

namespace App\Services\Reports;

use App\Models\Report;

class ReportAuditReference
{
    /** @return array<string, int|string|null> */
    public function details(string $type, array $parameters, ?Report $report, int $status, ?string $publicId = null): array
    {
        $publicId = $report?->uuid ?? $publicId;

        return ['status' => $status, 'report_type' => $type, 'report_public_id' => $publicId,
            'period_reference' => $this->period($parameters), 'instance_reference' => $publicId];
    }

    public function period(array $parameters): ?string
    {
        if (isset($parameters['year']) && preg_match('/^\d{4}$/D', (string) $parameters['year']) === 1) {
            $period = (string) $parameters['year'];
            if (isset($parameters['month']) && preg_match('/^(?:[1-9]|1[0-2])$/D', (string) $parameters['month']) === 1) {
                $period .= '-'.str_pad((string) $parameters['month'], 2, '0', STR_PAD_LEFT);
            }

            return $period;
        }
        foreach ([['start', 'end'], ['from', 'to']] as [$start, $end]) {
            if (isset($parameters[$start], $parameters[$end])
                && $this->isDate((string) $parameters[$start])
                && $this->isDate((string) $parameters[$end])) {
                return $parameters[$start].'/'.$parameters[$end];
            }
        }

        return null;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
