<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->enum('procurement_track', ['food', 'supplies'])->default('food')->after('status');
            $table->index(['procurement_track', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->dropIndex(['procurement_track', 'status']);
            $table->dropColumn('procurement_track');
        });
    }
};
