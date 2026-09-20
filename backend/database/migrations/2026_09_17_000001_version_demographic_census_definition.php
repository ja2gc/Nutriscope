<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demographic_census_periods', function (Blueprint $table): void {
            $table->unsignedTinyInteger('basis_version')->default(1)->after('source_count');
        });
    }

    public function down(): void
    {
        Schema::table('demographic_census_periods', function (Blueprint $table): void {
            $table->dropColumn('basis_version');
        });
    }
};
