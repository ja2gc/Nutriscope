<?php

namespace Tests\Unit;

use App\Models\ShoppingList;
use PHPUnit\Framework\TestCase;

class ShoppingListNameTest extends TestCase
{
    public function test_default_names_use_calendar_spans_without_duplicating_single_days(): void
    {
        $this->assertTrue(method_exists(ShoppingList::class, 'defaultName'));
        foreach ([
            ['manual', 'food', '2026-10-27', null, 'Shopping list - Oct 27, 2026'],
            ['manual', 'supplies', '2026-10-27', null, 'Supplies list - Oct 27, 2026'],
            ['suggested', 'food', '2026-10-27', '2026-10-27', 'Menu Shopping list - Oct 27, 2026'],
            ['suggested', 'food', '2026-10-27', '2026-10-29', 'Menu Shopping list - Oct 27–29, 2026'],
            ['suggested', 'food', '2026-10-31', '2026-11-02', 'Menu Shopping list - Oct 31 – Nov 2, 2026'],
            ['suggested', 'food', '2026-12-31', '2027-01-02', 'Menu Shopping list - Dec 31, 2026 – Jan 2, 2027'],
        ] as [$type, $track, $start, $end, $expected]) {
            $this->assertSame($expected, ShoppingList::defaultName($type, $track, $start, $end));
        }
    }
}
