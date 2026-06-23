<?php

namespace Database\Seeders;

use App\Models\FsItem;
use Illuminate\Database\Seeder;

/**
 * Food-service catalog seed — realistic items for a PH district-hospital kitchen,
 * drawn from the real PPA menu + Dietary Cash Book (pork, bangus, rice, saba,
 * munggo, mango, eggs, LPG, roll bag, paper meal box, etc.).
 *
 * Idempotent: keyed on name. NO link to food_items.
 *
 * Pricing model: purchase_price is ₱ per purchase_unit. For physical units
 * (kg→g, L→mL) the cost/base-unit is derived via UnitConverter; for count packs
 * (tray/pack/tank → pc/kg/g) set units_per_purchase.
 */
class FsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $row) {
            FsItem::updateOrCreate(['name' => $row['name']], $row);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function items(): array
    {
        // [name, kind, category, base_unit, purchase_unit, purchase_price, units_per_purchase]
        // kind: 'ingredient' (needs a recipe) | 'ready_to_eat' (standalone snack, placeable
        // directly in any meal slot) | 'supply' (non-food). Bought by kg / pack / bundle.
        $rows = [
            // ── Meat & fish ───────────────────────────────────────────────
            ['Pork (kasim)',          'ingredient', 'Meat',      'g',  'kg',   280, null],
            ['Pork (liempo)',         'ingredient', 'Meat',      'g',  'kg',   300, null],
            ['Ground pork',           'ingredient', 'Meat',      'g',  'kg',   290, null],
            ['Beef (cubes)',          'ingredient', 'Meat',      'g',  'kg',   360, null],
            ['Chicken (whole)',       'ingredient', 'Meat',      'g',  'kg',   200, null],
            ['Chicken fillet',        'ingredient', 'Meat',      'g',  'kg',   260, null],
            ['Bangus (milkfish)',     'ingredient', 'Fish',      'g',  'kg',   180, null],
            ['Egg',                   'ingredient', 'Poultry',   'pc', 'tray',  240, 30],

            // ── Grains & staples ─────────────────────────────────────────
            ['Rice',                  'ingredient', 'Grain',     'g',  'kg',    52, null],
            ['Munggo (mung bean)',    'ingredient', 'Grain',     'g',  'kg',    90, null],
            ['Macaroni',              'ingredient', 'Grain',     'g',  'pack',  85, 1000],

            // ── Vegetables ───────────────────────────────────────────────
            ['Assorted vegetables',   'ingredient', 'Vegetable', 'g',  'kg',    80, null],
            ['Pinakbet vegetables',   'ingredient', 'Vegetable', 'g',  'kg',    90, null],
            ['Onion',                 'ingredient', 'Vegetable', 'g',  'kg',   120, null],
            ['Garlic',                'ingredient', 'Vegetable', 'g',  'kg',   140, null],
            ['Ginger',                'ingredient', 'Vegetable', 'g',  'kg',   100, null],
            ['Tomato',                'ingredient', 'Vegetable', 'g',  'kg',    80, null],
            ['Carrot',                'ingredient', 'Vegetable', 'g',  'kg',    90, null],
            ['Potato',                'ingredient', 'Vegetable', 'g',  'kg',    90, null],
            ['Sayote',                'ingredient', 'Vegetable', 'g',  'kg',    60, null],
            ['Corn kernel',           'ingredient', 'Vegetable', 'g',  'kg',    70, null],

            // ── Fruit (ready-to-eat, sold by the bundle) ─────────────────
            ['Latundan banana',       'ready_to_eat', 'Fruit',   'pc', 'bundle', 50, 10],
            ['Saba banana',           'ready_to_eat', 'Fruit',   'pc', 'bundle', 60, 10],
            ['Ponkan',                'ready_to_eat', 'Fruit',   'pc', 'bundle', 96, 12],
            ['Ripe mango',            'ready_to_eat', 'Fruit',   'pc', 'kg',     120, 4],

            // ── Dairy & beverages ────────────────────────────────────────
            ['Fresh milk',            'ready_to_eat', 'Beverage', 'mL', 'pack',  90, 1000],
            ['Powdered milk',         'ingredient', 'Beverage',  'g',  'kg',   420, null],
            ['Milo',                  'ready_to_eat', 'Beverage', 'g',  'pack', 320, 1000],
            ['Coffee',                'ready_to_eat', 'Beverage', 'g',  'pack', 400, 1000],
            ['Yakult',                'ready_to_eat', 'Beverage', 'pc', 'pack',  55, 5],

            // ── Bakery & snacks ──────────────────────────────────────────
            ['Pandesal',              'ingredient', 'Bakery',    'pc', 'pack',  75, 25],
            ['Loaf bread',            'ingredient', 'Bakery',    'pc', 'pack',   65, 20],
            ['Brownie bite',          'ready_to_eat', 'Snack',   'pc', 'pack',  120, 24],
            ['Chooey toffee',         'ready_to_eat', 'Snack',   'pc', 'pack',   96, 24],

            // ── Condiments & cooking ─────────────────────────────────────
            ['Cooking oil',           'ingredient', 'Condiment', 'mL', 'L',     75, null],
            ['Soy sauce',             'ingredient', 'Condiment', 'mL', 'L',     60, null],
            ['Vinegar',               'ingredient', 'Condiment', 'mL', 'L',     45, null],
            ['Salt',                  'ingredient', 'Condiment', 'g',  'kg',    25, null],
            ['Sugar',                 'ingredient', 'Condiment', 'g',  'kg',    70, null],
            ['Cheez Whiz',            'ingredient', 'Condiment', 'g',  'jar',    85, 210],
            ['Mushroom (canned)',     'ingredient', 'Condiment', 'g',  'can',    45, 400],

            // ── Supplies (non-food) ──────────────────────────────────────
            ['LPG (cooking gas)',     'supply',     'Utility',    'kg', 'tank', 900, 11],
            ['Roll bag (garbage)',    'supply',     'Disposable', 'pc', 'roll',  80, 50],
            ['Paper meal box',        'supply',     'Disposable', 'pc', 'pack', 150, 50],
            ['Disposable spoon',      'supply',     'Disposable', 'pc', 'pack',  60, 100],
            ['Disposable fork',       'supply',     'Disposable', 'pc', 'pack',  60, 100],
            ['Plastic cup',           'supply',     'Disposable', 'pc', 'pack',  70, 100],
            ['Cling wrap',            'supply',     'Disposable', 'pc', 'pc',   120, null],
            ['Dishwashing liquid',    'supply',     'Cleaning',   'mL', 'L',     95, null],
            ['Hand soap',             'supply',     'Cleaning',   'mL', 'L',    110, null],
            ['Bleach',                'supply',     'Cleaning',   'mL', 'L',     55, null],
            ['Tissue paper',          'supply',     'Cleaning',   'pc', 'pack',  90, 12],
        ];

        return array_map(fn ($r) => [
            'name'               => $r[0],
            'kind'               => $r[1],
            'category'           => $r[2],
            'base_unit'          => $r[3],
            'purchase_unit'      => $r[4],
            'purchase_price'     => $r[5],
            'units_per_purchase' => $r[6],
            'is_active'          => true,
        ], $rows);
    }
}
