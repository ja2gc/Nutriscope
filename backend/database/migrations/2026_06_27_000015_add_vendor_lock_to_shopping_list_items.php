<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->timestamp('vendor_locked_at')->nullable()->after('fs_item_id');
            $table->foreignId('vendor_locked_by')->nullable()->constrained('users')->nullOnDelete()->after('vendor_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropForeignIdFor('vendor_locked_by');
            $table->dropColumn(['vendor_locked_at', 'vendor_locked_by']);
        });
    }
};
