<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6.3 — cache the optional Haiku monitoring narrative per visit-pair so the
 * same two visits never trigger a second AI call. ai_review_key is a hash of the
 * compared metric values; when it matches, the stored ai_review is reused.
 * Reversible (no data loss — both columns are additive + nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitorings', function (Blueprint $table) {
            $table->text('ai_review')->nullable()->after('ai_decision');
            $table->string('ai_review_key')->nullable()->after('ai_review');
        });
    }

    public function down(): void
    {
        Schema::table('monitorings', function (Blueprint $table) {
            $table->dropColumn(['ai_review', 'ai_review_key']);
        });
    }
};
