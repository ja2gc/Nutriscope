<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports Spec 4 — repurpose `reports` as the ARCHIVE.
 *
 * Under the browse-don't-generate model, on-demand renders are NOT persisted; the
 * only rows written are deliberate Archives — frozen, as-filed copies. Each archive
 * stores the rendered PDF (file_path) plus a `snapshot` of the branding/signatories/
 * period used, so a re-download serves the frozen copy without re-rendering.
 *
 * `type` and `status` were `enum`s. Their allowed values have grown twice already
 * (phase-5 generators; now 'archived') and the enum CHECK has to be patched per
 * driver every time — it bit us on sqlite, where the phase-5 types were never added.
 * Convert both to plain strings: the app validates report types (ReportService /
 * ReportBrowser) and owns the status lifecycle, so DB-level enums add only friction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->json('snapshot')->nullable()->after('parameters');
            $table->string('type')->change();
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        // The column types are intentionally left as strings — restoring a partial
        // enum would reject rows written under the wider value set.
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('snapshot');
        });
    }
};
