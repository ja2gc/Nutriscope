<?php

namespace App\Support;

final class MonitoringVisitType
{
    public const SCHEDULED_FOLLOW_UP = 'scheduled_follow_up';

    public const INPATIENT_REVIEW = 'inpatient_review';

    public const DISCHARGE_REVIEW = 'discharge_review';

    public const UNSCHEDULED_FOLLOW_UP = 'unscheduled_follow_up';

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::labels());
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::SCHEDULED_FOLLOW_UP => 'Scheduled follow-up',
            self::INPATIENT_REVIEW => 'Inpatient review',
            self::DISCHARGE_REVIEW => 'Discharge review',
            self::UNSCHEDULED_FOLLOW_UP => 'Unscheduled follow-up',
        ];
    }
}
