<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fs_items', function (Blueprint $table) {
            $table->timestamp('default_supplier_locked_at')->nullable()->after('default_supplier_id');
            $table->foreignId('default_supplier_locked_by')->nullable()->constrained('users')->nullOnDelete()->after('default_supplier_locked_at');
            $table->index('default_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('fs_items', function (Blueprint $table) {
            $table->dropIndex(['default_supplier_id']);
            $table->dropForeignIdFor('default_supplier_locked_by');
            $table->dropColumn(['default_supplier_locked_at', 'default_supplier_locked_by']);
        });
    }
};
