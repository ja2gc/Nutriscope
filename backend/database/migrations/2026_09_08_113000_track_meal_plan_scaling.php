<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plans', function (Blueprint $table): void {
            $table->boolean('needs_rescaling')->default(true)->after('generation_type');
            $table->timestamp('scaled_at')->nullable()->after('needs_rescaling');
        });

        // Existing auto-generated plans were already created against their saved
        // prescription. Only manual/template copies need the new explicit action.
        DB::table('meal_plans')->where('generation_type', 'auto')->update([
            'needs_rescaling' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table): void {
            $table->dropColumn(['needs_rescaling', 'scaled_at']);
        });
    }
};
