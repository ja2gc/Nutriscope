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
        Schema::create('shopping_lists', function (Blueprint $table) {

            $table->id();
            $table->foreignId('rnd_user_id')->references('id')->on('users');
            $table->string('name');
            $table->date('list_date');
            $table->enum('list_type', ['manual', 'suggested'])->default('manual');
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_lists');
    }
};
