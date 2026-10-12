<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->string('chewing_swallowing_details', 125)->nullable();
            $table->string('constipation_details', 125)->nullable();
            $table->string('diarrhea_details', 125)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn([
                'chewing_swallowing_details',
                'constipation_details',
                'diarrhea_details',
            ]);
        });
    }
};
