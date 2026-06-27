<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->unsignedInteger('baseline_servings')->nullable()->after('qty');
            $table->decimal('baseline_quantity', 12, 2)->nullable()->after('baseline_servings');
            $table->decimal('scaled_quantity', 12, 2)->nullable()->after('baseline_quantity');
            $table->string('scaled_unit')->nullable()->after('scaled_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn(['baseline_servings', 'baseline_quantity', 'scaled_quantity', 'scaled_unit']);
        });
    }
};
