<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demographic_census_periods', function (Blueprint $table): void {
            $table->id();
            $table->date('period_start')->unique();
            $table->date('period_end');
            $table->json('census');
            $table->unsignedInteger('source_count');
            $table->timestamp('frozen_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demographic_census_periods');
    }
};
