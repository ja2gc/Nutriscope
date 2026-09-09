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
        Schema::table('menu_cycle_days', function (Blueprint $table): void {
            $table->dropUnique('menu_cycle_day_meal_unique');
            $table->uuid('uuid')->nullable()->after('id');
            $table->unsignedSmallInteger('line_order')->default(1)->after('meal_type');
        });

        DB::table('menu_cycle_days')->orderBy('id')->eachById(function ($line): void {
            DB::table('menu_cycle_days')->where('id', $line->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('menu_cycle_days', function (Blueprint $table): void {
            $table->unique('uuid', 'menu_cycle_days_uuid_unique');
            $table->unique(
                ['menu_cycle_id', 'day_of_week', 'meal_type', 'line_order'],
                'menu_cycle_day_meal_line_unique'
            );
        });

        Schema::table('menu_cycle_template_days', function (Blueprint $table): void {
            $table->unsignedSmallInteger('line_order')->default(1)->after('meal_type');
            $table->unique(
                ['template_id', 'day_of_week', 'meal_type', 'line_order'],
                'menu_cycle_template_day_line_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('menu_cycle_template_days', function (Blueprint $table): void {
            $table->dropUnique('menu_cycle_template_day_line_unique');
            $table->dropColumn('line_order');
        });

        Schema::table('menu_cycle_days', function (Blueprint $table): void {
            $table->dropUnique('menu_cycle_day_meal_line_unique');
            $table->dropUnique('menu_cycle_days_uuid_unique');
            $table->dropColumn(['uuid', 'line_order']);
        });
    }
};
