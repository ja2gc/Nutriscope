<?php

namespace App\Services\Reports\Generators;

use App\Models\MenuCycle;
use App\Models\Report;
use App\Services\MenuCycleCostService;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;

/**
 * Menu Calendar — the same menu-cycle data as the PPA, skinned as a printable
 * Mon→Sun grid (meals down the side, days across the top) to post in the kitchen.
 * A layout variant of the PPA (§2.7), reusing its entry-grouping helper.
 */
class MenuCalendarGenerator implements ReportGenerator
{
    private const WEEK = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function type(): string
    {
        return 'menu_calendar';
    }

    public function view(): string
    {
        return 'reports.menu-calendar';
    }

    public function paper(): array
    {
        return ['a4', 'landscape'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];
        $cycle  = MenuCycle::with('days.recipe', 'days.fsItem')->findOrFail($params['menu_cycle_id']);

        $entries  = ProgramProjectActivityGenerator::groupEntries($cycle);
        $dayCount = max(1, min(7, (int) ($cycle->cycle_days ?: 7)));
        $days     = array_slice(self::WEEK, 0, $dayCount);

        // grid[meal_type][day_of_week] => [names]
        $grid = [];
        foreach (ProgramProjectActivityGenerator::MEAL_ORDER as $meal) {
            foreach ($days as $day) {
                $grid[$meal][$day] = [];
            }
        }
        foreach ($entries as $day => $slots) {
            foreach ($slots as $slot) {
                $label = ProgramProjectActivityGenerator::mealLabel($slot['meal_type']);
                if (isset($grid[$label][$day])) {
                    $grid[$label][$day][] = $slot['name'];
                }
            }
        }

        $start = $cycle->week_start_date ? Carbon::parse($cycle->week_start_date) : null;
        $dates = [];
        if ($start) {
            foreach ($days as $i => $day) {
                $dates[$day] = $start->copy()->addDays($i)->format('M j');
            }
        }

        $cost = MenuCycleCostService::forReport($cycle);

        return [
            'cycle'  => $cycle,
            'meals'  => ProgramProjectActivityGenerator::MEAL_ORDER,
            'days'   => $days,
            'dates'  => $dates,
            'grid'   => $grid,
            'cost'   => $cost,
        ];
    }
}
