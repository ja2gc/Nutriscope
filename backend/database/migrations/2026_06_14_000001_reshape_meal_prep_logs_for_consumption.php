<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('meal_prep_log_lines');
        Schema::dropIfExists('meal_prep_logs');

        Schema::create('meal_prep_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_cycle_id')->constrained('menu_cycles')->cascadeOnDelete();
            $table->date('service_date');
            $table->enum('status', ['completed', 'reversed'])->default('completed');
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('total_value', 12, 2)->default(0);
            $table->boolean('has_shortfall')->default(false);
            $table->timestamps();

            $table->unique(['menu_cycle_id', 'service_date']);
        });

        Schema::create('meal_prep_log_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_prep_log_id')->constrained('meal_prep_logs')->cascadeOnDelete();
            $table->foreignId('fs_item_id')->constrained('fs_items')->cascadeOnDelete();
            $table->decimal('qty_base', 12, 2);     // deducted, in base unit
            $table->string('unit', 20);
            $table->decimal('unit_cost', 12, 6);     // ₱/base at time of consumption (snapshot)
            $table->decimal('line_value', 12, 2);
            $table->decimal('shortfall_qty', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_prep_log_lines');
        Schema::dropIfExists('meal_prep_logs');

        // Restore the original orphaned shape.
        Schema::create('meal_prep_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fss_user_id')->references('id')->on('users');
            $table->foreignId('menu_cycle_day_id')->constrained()->references('id')->on('menu_cycle_days');
            $table->decimal('prepared_quantity', 8, 2)->nullable();
            $table->enum('status', ['done', 'pending'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
