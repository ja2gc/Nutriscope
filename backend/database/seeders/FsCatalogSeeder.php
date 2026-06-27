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
        // [name, kind, category, unit, cost_per_unit]
        // Single-unit catalog model (plan): each item has ONE unit and a cost per that
        // unit. Recipes may use a different convertible unit (g↔kg, mL↔L) — the
        // UnitConverter handles the rate. Count units (pc) are non-convertible: recipes
        // use the same unit. Supplies have no user unit; stored as a generic 'unit'.
        // kind: 'ingredient' (food, including single items) | 'supply' (non-food).
        $rows = [
            // ── Meat & fish (per kg) ──────────────────────────────────────
            ['Pork (kasim)',          'ingredient', 'Meat',      'kg', 280],
            ['Pork (liempo)',         'ingredient', 'Meat',      'kg', 300],
            ['Ground pork',           'ingredient', 'Meat',      'kg', 290],
            ['Beef (cubes)',          'ingredient', 'Meat',      'kg', 360],
            ['Chicken (whole)',       'ingredient', 'Meat',      'kg', 200],
            ['Chicken fillet',        'ingredient', 'Meat',      'kg', 260],
            ['Bangus (milkfish)',     'ingredient', 'Fish',      'kg', 180],
            ['Egg',                   'ingredient', 'Poultry',   'pc', 8],

            // ── Grains & staples (per kg) ─────────────────────────────────
            ['Rice',                  'ingredient', 'Grain',     'kg', 52],
            ['Munggo (mung bean)',    'ingredient', 'Grain',     'kg', 90],
            ['Macaroni',              'ingredient', 'Grain',     'kg', 85],

            // ── Vegetables (per kg) ───────────────────────────────────────
            ['Assorted vegetables',   'ingredient', 'Vegetable', 'kg', 80],
            ['Pinakbet vegetables',   'ingredient', 'Vegetable', 'kg', 90],
            ['Onion',                 'ingredient', 'Vegetable', 'kg', 120],
            ['Garlic',                'ingredient', 'Vegetable', 'kg', 140],
            ['Ginger',                'ingredient', 'Vegetable', 'kg', 100],
            ['Tomato',                'ingredient', 'Vegetable', 'kg', 80],
            ['Carrot',                'ingredient', 'Vegetable', 'kg', 90],
            ['Potato',                'ingredient', 'Vegetable', 'kg', 90],
            ['Sayote',                'ingredient', 'Vegetable', 'kg', 60],
            ['Corn kernel',           'ingredient', 'Vegetable', 'kg', 70],

            // ── Fruit (single items, per piece) ───────────────────────────
            ['Latundan banana',       'ingredient', 'Fruit',     'pc', 5],
            ['Saba banana',           'ingredient', 'Fruit',     'pc', 6],
            ['Ponkan',                'ingredient', 'Fruit',     'pc', 8],
            ['Ripe mango',            'ingredient', 'Fruit',     'pc', 30],

            // ── Dairy & beverages ─────────────────────────────────────────
            ['Fresh milk',            'ingredient', 'Beverage',  'L',  90],
            ['Powdered milk',         'ingredient', 'Beverage',  'kg', 420],
            ['Milo',                  'ingredient', 'Beverage',  'kg', 320],
            ['Coffee',                'ingredient', 'Beverage',  'kg', 400],
            ['Yakult',                'ingredient', 'Beverage',  'pc', 11],

            // ── Bakery & snacks (per piece) ───────────────────────────────
            ['Pandesal',              'ingredient', 'Bakery',    'pc', 3],
            ['Loaf bread',            'ingredient', 'Bakery',    'pc', 3.25],
            ['Brownie bite',          'ingredient', 'Snack',     'pc', 5],
            ['Chooey toffee',         'ingredient', 'Snack',     'pc', 4],

            // ── Condiments & cooking ──────────────────────────────────────
            ['Cooking oil',           'ingredient', 'Condiment', 'L',  75],
            ['Soy sauce',             'ingredient', 'Condiment', 'L',  60],
            ['Vinegar',               'ingredient', 'Condiment', 'L',  45],
            ['Salt',                  'ingredient', 'Condiment', 'kg', 25],
            ['Sugar',                 'ingredient', 'Condiment', 'kg', 70],
            ['Cheez Whiz',            'ingredient', 'Condiment', 'kg', 405],
            ['Mushroom (canned)',     'ingredient', 'Condiment', 'kg', 112.50],

            // ── Supplies (non-food, cost per unit, no user unit) ──────────
            ['LPG (cooking gas)',     'supply',     'Utility',    'unit', 900],
            ['Roll bag (garbage)',    'supply',     'Disposable', 'unit', 1.60],
            ['Paper meal box',        'supply',     'Disposable', 'unit', 3],
            ['Disposable spoon',      'supply',     'Disposable', 'unit', 0.60],
            ['Disposable fork',       'supply',     'Disposable', 'unit', 0.60],
            ['Plastic cup',           'supply',     'Disposable', 'unit', 0.70],
            ['Cling wrap',            'supply',     'Disposable', 'unit', 120],
            ['Dishwashing liquid',    'supply',     'Cleaning',   'unit', 95],
            ['Hand soap',             'supply',     'Cleaning',   'unit', 110],
            ['Bleach',                'supply',     'Cleaning',   'unit', 55],
            ['Tissue paper',          'supply',     'Cleaning',   'unit', 7.50],
        ];

        // Single unit + cost per unit: purchase_unit == base_unit, 1 per purchase, so the
        // model's unit_cost accessor returns the entered cost directly.
        return array_map(fn ($r) => [
            'name'               => $r[0],
            'kind'               => $r[1],
            'category'           => $r[2],
            'base_unit'          => $r[3],
            'purchase_unit'      => $r[3],
            'purchase_price'     => $r[4],
            'units_per_purchase' => 1,
            'is_active'          => true,
        ], $rows);
    }
}
