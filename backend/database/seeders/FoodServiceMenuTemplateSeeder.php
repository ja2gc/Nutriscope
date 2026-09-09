<?php

namespace Database\Seeders;

use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\MenuCycleTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FoodServiceMenuTemplateSeeder extends Seeder
{
    private const SLOTS = ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'];

    public function run(): void
    {
        $rnd = User::query()->where('role', 'RND')->first();
        if (! $rnd) {
            throw new RuntimeException('Food-service templates require a seeded RND user.');
        }

        $recipes = FoodServiceRecipe::query()->get()->keyBy('name');
        $items = FsItem::query()->get()->keyBy('name');
        DB::transaction(function () use ($rnd, $recipes, $items): void {
            foreach ($this->templates() as $name => $definition) {
                $template = MenuCycleTemplate::updateOrCreate(
                    ['name' => $name],
                    [
                        'rnd_user_id' => $rnd->id,
                        'description' => $definition['description'],
                        'cycle_days' => 7,
                    ],
                );
                $template->days()->delete();

                foreach ($definition['plan'] as $day => $plannedItems) {
                    foreach (self::SLOTS as $index => $meal) {
                        $itemName = $plannedItems[$index];
                        $recipe = $recipes->get($itemName);
                        $item = $items->get($itemName);
                        if (! $recipe && ! $item) {
                            throw new RuntimeException("Food-service template item '{$itemName}' is missing.");
                        }

                        $template->days()->create([
                            'day_of_week' => $day,
                            'meal_type' => $meal,
                            'line_order' => 1,
                            'recipe_id' => $recipe?->id,
                            'fs_item_id' => $item?->id,
                            'quantity' => $item ? $this->menuItemQuantity($itemName) : 1,
                        ]);
                    }
                }
            }
        });
    }

    /** Per-patient amount in the catalog item's purchase unit. */
    private function menuItemQuantity(string $name): float
    {
        return match ($name) {
            'Coffee' => 0.02,
            'Milo' => 0.025,
            'Fresh milk' => 0.20,
            default => 1.0,
        };
    }

    /** @return array<string,array{description:string,plan:array<string,list<string>>}> */
    private function templates(): array
    {
        return [
            'Balanced Filipino Hospital Menu' => [
                'description' => 'Balanced seven-day hospital meal rotation.',
                'plan' => [
                    'Monday' => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Pinakbet', 'Latundan banana', 'Chicken Sisig'],
                    'Tuesday' => ['Sopas', 'Coffee', 'Chicken Fillet w/ Mushroom Sauce', 'Saba banana', 'Pork Picadillo'],
                    'Wednesday' => ['Mami Noodle Soup', 'Fresh milk', 'Beef Caldereta', 'Ponkan', 'Paksiw na Bangus'],
                    'Thursday' => ['Pandesal with Boiled Egg', 'Milo', 'Chicken with Lemongrass', 'Saba banana', 'Pork Strips Oriental with Corn'],
                    'Friday' => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Picadillo', 'Brownie bite', 'Chicken Fillet w/ Mushroom Sauce'],
                    'Saturday' => ['Sopas', 'Coffee', 'Paksiw na Bangus', 'Chooey toffee', 'Beef Caldereta'],
                    'Sunday' => ['Pandesal with Boiled Egg', 'Milo', 'Chicken Sisig', 'Latundan banana', 'Pork Pinakbet'],
                ],
            ],
            'Fish and Chicken Lighter Menu' => [
                'description' => 'Fish- and chicken-forward seven-day meal rotation.',
                'plan' => [
                    'Monday' => ['Sopas', 'Coffee', 'Chicken Sisig', 'Latundan banana', 'Paksiw na Bangus'],
                    'Tuesday' => ['Mami Noodle Soup', 'Fresh milk', 'Chicken with Lemongrass', 'Saba banana', 'Chicken Fillet w/ Mushroom Sauce'],
                    'Wednesday' => ['Cheezwhiz Sandwich', 'Yakult', 'Paksiw na Bangus', 'Ponkan', 'Chicken Sisig'],
                    'Thursday' => ['Pandesal with Boiled Egg', 'Milo', 'Chicken Fillet w/ Mushroom Sauce', 'Saba banana', 'Chicken with Lemongrass'],
                    'Friday' => ['Sopas', 'Coffee', 'Chicken Sisig', 'Brownie bite', 'Paksiw na Bangus'],
                    'Saturday' => ['Mami Noodle Soup', 'Fresh milk', 'Chicken with Lemongrass', 'Chooey toffee', 'Chicken Fillet w/ Mushroom Sauce'],
                    'Sunday' => ['Pandesal with Boiled Egg', 'Milo', 'Paksiw na Bangus', 'Latundan banana', 'Chicken Sisig'],
                ],
            ],
            'Affordable Plant-Forward Rotation' => [
                'description' => 'Budget-conscious rotation centered on munggo, vegetables, eggs, and modest animal protein.',
                'plan' => [
                    'Monday' => ['Pandesal with Boiled Egg', 'Latundan banana', 'Ginisang Munggo', 'Ponkan', 'Ginisang Gulay'],
                    'Tuesday' => ['Sopas', 'Saba banana', 'Pork Pinakbet', 'Fresh milk', 'Ginisang Munggo'],
                    'Wednesday' => ['Cheezwhiz Sandwich', 'Ponkan', 'Ginisang Gulay', 'Latundan banana', 'Paksiw na Bangus'],
                    'Thursday' => ['Pandesal with Boiled Egg', 'Saba banana', 'Ginisang Munggo', 'Fresh milk', 'Pork Pinakbet'],
                    'Friday' => ['Sopas', 'Latundan banana', 'Ginisang Gulay', 'Ponkan', 'Ginisang Munggo'],
                    'Saturday' => ['Cheezwhiz Sandwich', 'Saba banana', 'Paksiw na Bangus', 'Fresh milk', 'Ginisang Gulay'],
                    'Sunday' => ['Pandesal with Boiled Egg', 'Ponkan', 'Ginisang Munggo', 'Latundan banana', 'Pork Pinakbet'],
                ],
            ],
        ];
    }
}
