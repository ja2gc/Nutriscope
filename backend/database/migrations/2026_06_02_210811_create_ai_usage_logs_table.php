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
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users');
            $table->string('model');
            $table->integer('tokens_input');
            $table->integer('tokens_output');
            $table->integer('tokens_total');
            $table->string('endpoint');
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
