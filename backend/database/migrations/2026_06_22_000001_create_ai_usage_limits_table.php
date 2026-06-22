<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daily_token_limit')->nullable();
            $table->unsignedBigInteger('monthly_token_limit')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_limits');
    }
};
