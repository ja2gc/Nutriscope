<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedule_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('daily')->default(false);
            $table->boolean('weekly')->default(false);
            $table->boolean('monthly')->default(false);
            $table->timestamps();
        });

        DB::table('backup_schedule_settings')->insert([
            'id' => 1,
            'daily' => false,
            'weekly' => false,
            'monthly' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedule_settings');
    }
};
