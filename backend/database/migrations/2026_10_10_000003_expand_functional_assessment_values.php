<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->string('functional_assessment', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep the expanded text column so saved new choices remain intact.
    }
};
