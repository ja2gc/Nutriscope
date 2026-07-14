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
        Schema::create('calendar_events', function (Blueprint $table) {

            $table->id();
            $table->foreignId('user_id')->references('id')->on('users');
            $table->string('title');
            $table->enum('type', ['manual', 'system', 'followup']);
            $table->string('source_module')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('event_date');
            $table->enum('status', ['pending', 'completed', 'overdue'])->default('pending');
            $table->boolean('deletable')->default(true);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
