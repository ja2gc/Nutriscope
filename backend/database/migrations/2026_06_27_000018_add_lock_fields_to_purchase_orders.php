<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('structural_locked_at')->nullable()->after('completed_at');
            $table->timestamp('final_locked_at')->nullable()->after('structural_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['structural_locked_at', 'final_locked_at']);
        });
    }
};
