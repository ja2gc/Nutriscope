<?php

namespace App\Services;

/**
 * Turns a menu cycle's whole-cycle ingredient usage (from MenuCycleCostService)
 * into a suggested shopping list scaled to an arbitrary purchasing day span.
 */
class ProcurementService
{
    /**
     * @param  array<int,array{fs_item_id:int,name:string,unit:string,quantity:float,cost:float}> $ingredientUsage
     * @return array<int,array{fs_item_id:int,name:string,unit:string,qty:float,total:float}>
     */
    public static function suggestedItems(array $ingredientUsage, int $cycleDays, int $spanDays): array
    {
        $cycle  = $cycleDays > 0 ? $cycleDays : max(1, $spanDays);
        $factor = max(0, $spanDays) / $cycle;

        return array_map(fn ($u) => [
            'fs_item_id' => (int) $u['fs_item_id'],
            'name'       => $u['name'],
            'unit'       => $u['unit'],
            'qty'        => round((float) $u['quantity'] * $factor, 2),
            'total'      => round((float) $u['cost'] * $factor, 2),
        ], $ingredientUsage);
    }
}
