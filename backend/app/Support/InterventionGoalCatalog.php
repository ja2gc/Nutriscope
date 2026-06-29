<?php

namespace App\Support;

class InterventionGoalCatalog
{
    /**
     * @return array<string,array<int,string>>
     */
    public static function stages(): array
    {
        return config('clinical.intervention_goal_stages', []);
    }

    /**
     * @return array<int,string>
     */
    public static function goalTypes(): array
    {
        return array_keys(self::stages());
    }

    public static function goalExists(?string $goalType): bool
    {
        return is_string($goalType) && array_key_exists($goalType, self::stages());
    }

    public static function stageIsValid(?string $goalType, mixed $stage): bool
    {
        if (! self::goalExists($goalType)) {
            return false;
        }

        $allowed = self::stages()[$goalType];
        if ($allowed === []) {
            return $stage === null || $stage === '';
        }

        return is_string($stage) && in_array($stage, $allowed, true);
    }
}
