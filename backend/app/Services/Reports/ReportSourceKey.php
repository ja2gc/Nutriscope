<?php

namespace App\Services\Reports;

class ReportSourceKey
{
    public static function for(string $type, array $parameters): ?string
    {
        $keys = match ($type) {
            'procurement_pack', 'program_project_activity' => ['purchase_order_id'],
            'menu_calendar' => ['menu_cycle_id'],
            'accomplishment_report' => ['start', 'end', 'fss_user_id'],
            default => [],
        };
        if ($keys === []) {
            return null;
        }
        $source = [];
        foreach ($keys as $key) {
            if ($key === 'fss_user_id') {
                $source[$key] = (string) ($parameters[$key] ?? 'all');
            } elseif (! isset($parameters[$key])) {
                return null;
            } else {
                $source[$key] = (string) $parameters[$key];
            }
        }

        return hash('sha256', $type.'|'.json_encode($source, JSON_THROW_ON_ERROR));
    }
}
