<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnoses', function (Blueprint $table): void {
            $table->enum('domain', ['NI', 'NC', 'NB', 'NO'])->change();
        });
    }

    public function down(): void
    {
        // Keep the expanded domain so rolling back cannot discard clinician decisions.
    }
};
