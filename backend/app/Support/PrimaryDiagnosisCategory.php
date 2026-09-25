<?php

namespace App\Support;

final class PrimaryDiagnosisCategory
{
    public const CARDIOVASCULAR = 'Cardiovascular';

    public const RENAL = 'Renal';

    public const DIABETES = 'Diabetes';

    public const OBESITY = 'Obesity';

    public const MALNUTRITION = 'Malnutrition';

    public const SURGERY_TRAUMA = 'Surgery / Trauma';

    public const LIVER = 'Liver';

    public const CANCER = 'Cancer';

    public const PREGNANCY_LACTATION = 'Pregnancy / Lactation';

    public const OTHER = 'Other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::CARDIOVASCULAR,
            self::RENAL,
            self::DIABETES,
            self::OBESITY,
            self::MALNUTRITION,
            self::SURGERY_TRAUMA,
            self::LIVER,
            self::CANCER,
            self::PREGNANCY_LACTATION,
            self::OTHER,
        ];
    }

    public static function isAllowed(?string $value): bool
    {
        return $value !== null && in_array($value, self::values(), true);
    }
}
