<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the superseded patient marker from databases that ran the earlier draft migration.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('patients', 'is_demo')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn('is_demo');
        });
    }

    /**
     * The removed marker is intentionally not restored.
     */
    public function down(): void {}
};
