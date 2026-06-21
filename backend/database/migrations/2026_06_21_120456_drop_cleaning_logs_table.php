<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the cleaning_logs table (retired in R2 scope-enforcement pass — replaced by
     * the diet-list / accomplishment capture built in commit 93920e6).
     */
    public function up(): void
    {
        Schema::dropIfExists('cleaning_logs');
    }

    /**
     * Recreate the table so the migration is reversible.
     */
    public function down(): void
    {
        Schema::create('cleaning_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fss_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('category')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('cleaned_at')->nullable();
            $table->timestamps();
        });
    }
};
