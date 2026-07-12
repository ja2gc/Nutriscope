<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table): void {
            $table->dropColumn(['quantity_in_stock', 'unit', 'unit_price', 'notes']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Retired inventory stock fields cannot be restored safely.');
    }
};
