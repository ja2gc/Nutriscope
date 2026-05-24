<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('dob');
            $table->enum('sex', ['Male', 'Female']);
            $table->string('religion')->nullable();
            $table->text('address')->nullable();
            $table->string('contact')->nullable();
            $table->string('physician')->nullable();
            $table->date('admission_date');
            $table->text('medical_diagnosis')->nullable();
            $table->string('ward')->nullable();
            $table->enum('status', ['Active', 'Discharged', 'Transferred'])->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
