<?php

namespace App\Services\Reports\Generators;

use App\Models\MealPlan;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;

/**
 * Patient Menu Plan — a patient's ADIME meal plan rendered as a Mon→Sun calendar PDF
 * (meals down the side, days across). Reads the persisted meal plan; no recompute.
 */
class PatientMenuPlanGenerator implements ReportGenerator
{
    private const WEEK = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    private const MEALS = ['Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner'];

    public function type(): string
    {
        return 'patient_menu_plan';
    }

    public function view(): string
    {
        return 'reports.patient-menu-plan';
    }

    public function paper(): array
    {
        return ['a4', 'landscape'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];

        $plan = MealPlan::with(['patient', 'days.items.foodItem', 'days.items.recipe'])
            ->when(! empty($params['meal_plan_id']), fn ($q) => $q->whereKey($params['meal_plan_id']))
            ->when(empty($params['meal_plan_id']) && ! empty($params['patient_id']),
                fn ($q) => $q->where('patient_id', $params['patient_id']))
            ->latest('week_start_date')
            ->firstOrFail();

        $grid = [];
        foreach (self::MEALS as $meal) {
            foreach (self::WEEK as $day) {
                $grid[$meal][$day] = [];
            }
        }

        foreach ($plan->days as $day) {
            foreach ($day->items as $item) {
                $name = $item->foodItem?->name ?? $item->recipe?->name;
                if ($name && isset($grid[$day->meal_type][$day->day_of_week])) {
                    $grid[$day->meal_type][$day->day_of_week][] = [
                        'name'     => $name,
                        'quantity' => $item->quantity,
                        'unit'     => $item->unit,
                    ];
                }
            }
        }

        return [
            'plan'    => $plan,
            'patient' => $plan->patient,
            'meals'   => self::MEALS,
            'days'    => self::WEEK,
            'grid'    => $grid,
        ];
    }
}
