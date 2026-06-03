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
        Schema::create('reports', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users');
            $table->string('title');
            $table->enum('type', [
                'adime_individual', 'adime_aggregate', 'ncp_census', 'inventory', 'inventory_report',
                'budget', 'budget_report', 'procurement', 'menu_cycle', 'menu_cycle_report',
                'patient_menu_plan', 'inspection_report', 'marketing_statement', 'marketing_summary'
            ]);
            $table->json('filters')->nullable();
            $table->json('parameters')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'queued', 'generating', 'completed', 'failed'])->default('pending');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
