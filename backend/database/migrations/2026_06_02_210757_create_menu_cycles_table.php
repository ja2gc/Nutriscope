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
        Schema::create('menu_cycles', function (Blueprint $table) {

            $table->id();
            $table->foreignId('rnd_user_id')->references('id')->on('users');
            $table->date('week_start_date');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->date('activation_date')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_cycles');
    }
};
