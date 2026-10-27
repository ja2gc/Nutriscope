<?php

namespace Tests\Unit;

use App\Support\NutritionSupport;
use PHPUnit\Framework\TestCase;

class NutritionSupportTest extends TestCase
{
    public function test_support_modes_keep_no_menu_distinct_from_oral_and_formulas(): void
    {
        $this->assertSame(['oral', 'tube_feeding', 'no_food_menu'], NutritionSupport::VALUES);
        $this->assertSame('Per Orem', NutritionSupport::label('oral'));
        $this->assertSame('Tube Feeding', NutritionSupport::label('tube_feeding'));
        $this->assertSame('NPO / TPN', NutritionSupport::label('no_food_menu'));
        $this->assertSame('Not recorded', NutritionSupport::label(null));
    }
}
