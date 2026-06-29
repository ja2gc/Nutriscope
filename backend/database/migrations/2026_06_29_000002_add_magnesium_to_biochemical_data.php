<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biochemical_data', function (Blueprint $table): void {
            $table->decimal('magnesium', 6, 2)->nullable()->after('phosphate');
        });
    }

    public function down(): void
    {
        Schema::table('biochemical_data', function (Blueprint $table): void {
            $table->dropColumn('magnesium');
        });
    }
};
