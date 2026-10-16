<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $verifiedMeals = [
            'Cheezwhiz Sandwich', 'Pandesal with Boiled Egg', 'Sopas', 'Mami Noodle Soup',
            'Pork Pinakbet', 'Pork Picadillo', 'Pork Strips Oriental with Corn', 'Beef Caldereta',
            'Chicken Fillet w/ Mushroom Sauce', 'Chicken Sisig', 'Chicken with Lemongrass',
            'Paksiw na Bangus', 'Ginisang Munggo', 'Ginisang Gulay',
        ];
        DB::transaction(function () use ($verifiedMeals): void {
            foreach (DB::table('food_service_recipes')->orderBy('id')->lockForUpdate()->get(['id', 'name', 'category']) as $recipe) {
                if (in_array($recipe->category, ['meal', 'snack', 'beverage'], true)) {
                    continue;
                }
                if (! in_array($recipe->category, ['breakfast', 'lunch', 'dinner'], true)
                    && ! (($recipe->category === null || $recipe->category === '') && in_array($recipe->name, $verifiedMeals, true))) {
                    throw new RuntimeException('Classify the unverified Food Service food before correcting categories.');
                }
                DB::table('food_service_recipes')->where('id', $recipe->id)->update(['category' => 'meal']);
            }
        });
    }

    /**
     * Verified category correction is forward-only.
     */
    public function down(): void {}
};
