<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropUnique(['recipe_id']);
            $table->dropColumn('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
        });
    }
};
