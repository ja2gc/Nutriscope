<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\Report;
use App\Services\Reports\Generators\MenuCalendarGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuCalendarReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_snack_rows_are_omitted_from_the_saved_weekly_menu(): void
    {
        $cycle = MenuCycle::factory()->create();
        $this->addMeal($cycle, 'Monday', 'breakfast', 'Vegetable soup');
        $this->addMeal($cycle, 'Tuesday', 'lunch', 'Chicken adobo');

        $data = app(MenuCalendarGenerator::class)->data($this->reportFor($cycle));

        $this->assertSame(['Breakfast', 'Lunch', 'Dinner'], $data['meals']);
        $this->assertSame(['Vegetable soup'], $data['grid']['Breakfast']['Monday']);
        $this->assertSame(['Chicken adobo'], $data['grid']['Lunch']['Tuesday']);
    }

    public function test_a_snack_row_remains_when_any_day_has_a_saved_snack(): void
    {
        $cycle = MenuCycle::factory()->create();
        $this->addMeal($cycle, 'Saturday', 'am_snack', 'Banana');

        $data = app(MenuCalendarGenerator::class)->data($this->reportFor($cycle));

        $this->assertSame(['Breakfast', 'AM Snack', 'Lunch', 'Dinner'], $data['meals']);
        $this->assertSame(['Banana'], $data['grid']['AM Snack']['Saturday']);
        $this->assertSame([], $data['grid']['AM Snack']['Monday']);
    }

    private function reportFor(MenuCycle $cycle): Report
    {
        return new Report(['type' => 'menu_calendar', 'parameters' => ['menu_cycle_id' => $cycle->id]]);
    }

    private function addMeal(MenuCycle $cycle, string $day, string $meal, string $name): void
    {
        $item = FsItem::factory()->create(['name' => $name]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => $day,
            'meal_type' => $meal,
            'fs_item_id' => $item->id,
            'quantity' => 1,
        ]);
    }
}
