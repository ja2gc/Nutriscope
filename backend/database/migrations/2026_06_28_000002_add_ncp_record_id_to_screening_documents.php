<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link supporting documents directly to the NCP cycle instead of only through an
 * assessment. This lets attachment upload work without force-creating an empty
 * assessment row (AS-02), which previously let any upload satisfy the Assessment
 * gate. Existing rows are backfilled from their assessment's ncp_record_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_documents', function (Blueprint $table) {
            $table->foreignId('ncp_record_id')->nullable()->after('patient_id')
                ->constrained()->nullOnDelete();
        });

        // Backfill cycle link from the assessment each document already points to.
        DB::statement(
            'UPDATE screening_documents sd
             JOIN assessments a ON a.id = sd.assessment_id
             SET sd.ncp_record_id = a.ncp_record_id
             WHERE sd.ncp_record_id IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('screening_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ncp_record_id');
        });
    }
};
