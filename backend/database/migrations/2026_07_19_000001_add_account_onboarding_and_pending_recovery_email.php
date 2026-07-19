<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->boolean('must_set_recovery_email')->default(false)->after('must_change_password');
            $table->timestamp('onboarding_skipped_at')->nullable()->after('must_set_recovery_email');
            $table->string('pending_recovery_email')->nullable()->unique()->after('recovery_email_verification_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['pending_recovery_email']);
            $table->dropColumn(['must_change_password', 'must_set_recovery_email', 'onboarding_skipped_at', 'pending_recovery_email']);
        });
    }
};
