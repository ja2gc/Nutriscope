<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->string('chewing_swallowing_details', 150)->nullable()->change();
            $table->string('constipation_details', 150)->nullable()->change();
            $table->string('diarrhea_details', 150)->nullable()->change();
        });
    }
};
