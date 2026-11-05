<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table): void {
            $table->dropColumn('ready_to_eat');
        });
    }

    public function down(): void
    {
        throw new LogicException('Retired ready-to-eat flags cannot be restored safely; use a forward migration.');
    }
};
