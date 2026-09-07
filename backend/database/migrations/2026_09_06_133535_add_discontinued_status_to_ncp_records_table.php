<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ncp_records', function (Blueprint $table): void {
            $table->enum('status', ['draft', 'active', 'completed', 'discontinued', 'discharged'])
                ->default('draft')
                ->change();
            $table->string('discontinuation_reason_code', 48)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ncp_records', function (Blueprint $table): void {
            $table->dropColumn('discontinuation_reason_code');
            $table->enum('status', ['draft', 'active', 'completed', 'discharged'])
                ->default('draft')
                ->change();
        });
    }
};
