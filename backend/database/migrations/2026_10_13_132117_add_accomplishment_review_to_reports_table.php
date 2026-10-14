<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('review_state', 20)->nullable();
            $table->unsignedInteger('review_version')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->index(['type', 'review_state', 'submitted_at'], 'reports_accomplishment_review_index');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_accomplishment_review_index');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_state', 'review_version', 'submitted_at', 'reviewed_at', 'rejection_reason']);
        });
    }
};
