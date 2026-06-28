<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('recovery_email')->nullable()->unique()->after('email');
            $table->timestamp('recovery_email_verified_at')->nullable()->after('recovery_email');
            $table->string('recovery_email_verification_code')->nullable()->after('recovery_email_verified_at');
            $table->timestamp('recovery_email_verification_expires_at')->nullable()->after('recovery_email_verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['recovery_email']);
            $table->dropColumn([
                'recovery_email',
                'recovery_email_verified_at',
                'recovery_email_verification_code',
                'recovery_email_verification_expires_at',
            ]);
        });
    }
};
