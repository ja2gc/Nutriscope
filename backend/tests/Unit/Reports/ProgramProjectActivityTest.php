<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\Generators\ProgramProjectActivityGenerator;
use PHPUnit\Framework\TestCase;

class ProgramProjectActivityTest extends TestCase
{
    /** The cycle's day_of_week entries, in meal order, mapped onto calendar dates. */
    public function test_builds_ordered_menu_days_from_cycle_entries(): void
    {
        $entries = [
            'Thursday' => [
                ['meal_type' => 'Lunch', 'name' => 'Pork Pinakbet'],
                ['meal_type' => 'Breakfast', 'name' => 'Cheezwhiz Sandwich'],
                ['meal_type' => 'Breakfast', 'name' => 'Yakult'],
                ['meal_type' => 'Dinner', 'name' => 'Paksiw na Bangus'],
            ],
        ];

        $calendar = [
            ['date' => '2026-06-05', 'day_of_week' => 'Thursday'],
        ];

        $days = ProgramProjectActivityGenerator::buildMenuDays($entries, $calendar);

        $this->assertCount(1, $days);
        $this->assertSame('2026-06-05', $days[0]['date']);
        $this->assertSame('05', $days[0]['label']);
        // Meals come back ordered Breakfast → Lunch → Dinner (canonical meal order).
        $this->assertSame(
            ['Cheezwhiz Sandwich', 'Yakult', 'Pork Pinakbet', 'Paksiw na Bangus'],
            array_column($days[0]['meals'], 'name'),
        );
    }

    public function test_skips_dates_with_no_planned_entries_gracefully(): void
    {
        $days = ProgramProjectActivityGenerator::buildMenuDays(
            ['Monday' => [['meal_type' => 'Lunch', 'name' => 'Adobo']]],
            [
                ['date' => '2026-06-08', 'day_of_week' => 'Monday'],
                ['date' => '2026-06-09', 'day_of_week' => 'Tuesday'],
            ],
        );

        $this->assertCount(2, $days);
        $this->assertSame(['Adobo'], array_column($days[0]['meals'], 'name'));
        $this->assertSame([], $days[1]['meals']);
    }

    public function test_calendar_dates_expands_inclusive_range_to_day_of_week(): void
    {
        $cal = ProgramProjectActivityGenerator::calendarDates('2026-06-05', '2026-06-08');

        $this->assertSame(
            ['Friday', 'Saturday', 'Sunday', 'Monday'],
            array_column($cal, 'day_of_week'),
        );
        $this->assertSame('2026-06-05', $cal[0]['date']);
        $this->assertSame('2026-06-08', $cal[3]['date']);
    }
}
