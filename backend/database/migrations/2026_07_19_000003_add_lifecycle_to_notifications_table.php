<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable()->after('read');
            $table->timestamp('opened_at')->nullable()->after('read_at');
            $table->timestamp('resolved_at')->nullable()->after('opened_at');
            $table->index(['type', 'opened_at'], 'notifications_type_opened_idx');
            $table->index(['type', 'resolved_at'], 'notifications_type_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_type_opened_idx');
            $table->dropIndex('notifications_type_resolved_idx');
            $table->dropColumn(['read_at', 'opened_at', 'resolved_at']);
        });
    }
};
