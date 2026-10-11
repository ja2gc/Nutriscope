<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->string('quantity_mode', 12)->default('calculated');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->dropColumn('quantity_mode');
        });
    }
};
