<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fs_items', function (Blueprint $table): void {
            $table->decimal('purchase_price', 14, 6)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('fs_items', function (Blueprint $table): void {
            $table->decimal('purchase_price', 10, 2)->default(0)->change();
        });
    }
};
