<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plan_templates', function (Blueprint $table): void {
            $table->string('disease_stage')->nullable()->after('goal_type');
        });

        Schema::create('meal_plan_template_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('template_day_id')->constrained('meal_plan_template_days')->cascadeOnDelete();
            $table->foreignId('food_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fdc_id', 20)->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->default('serving');
            $table->json('nutrient_snapshot')->nullable();
            $table->boolean('ai_suggested')->default(false);
            $table->unsignedSmallInteger('line_order')->default(1);
            $table->timestamps();
            $table->unique(['template_day_id', 'line_order'], 'template_item_line_unique');
        });

        $now = now();
        DB::table('meal_plan_template_days')
            ->where(fn ($query) => $query->whereNotNull('food_item_id')->orWhereNotNull('recipe_id'))
            ->orderBy('id')
            ->chunkById(500, function ($days) use ($now): void {
                $rows = [];
                foreach ($days as $day) {
                    $rows[] = [
                        'uuid' => (string) Str::uuid(),
                        'template_day_id' => $day->id,
                        'food_item_id' => $day->food_item_id,
                        'recipe_id' => $day->recipe_id,
                        'quantity' => $day->quantity,
                        'unit' => $day->unit,
                        'line_order' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('meal_plan_template_items')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_template_items');
        Schema::table('meal_plan_templates', function (Blueprint $table): void {
            $table->dropColumn('disease_stage');
        });
    }
};
