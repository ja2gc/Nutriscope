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
        Schema::create('marketing_summaries', function (Blueprint $table) {

            $table->id();
            $table->foreignId('marketing_statement_id')->constrained()->cascadeOnDelete();
            $table->date('date_purchased')->nullable();
            $table->date('inclusive_start')->nullable();
            $table->date('inclusive_end')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('certified_by')->nullable();
            $table->string('certified_by_title')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_summaries');
    }
};
