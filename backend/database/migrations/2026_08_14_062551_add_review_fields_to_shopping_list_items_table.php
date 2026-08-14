<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->string('source', 20)->default('generated')->after('ingredient_name');
            $table->boolean('included_in_po')->default(true)->after('source');
            $table->string('exclusion_note')->nullable()->after('included_in_po');
            $table->decimal('qty', 12, 3)->change();
            $table->decimal('purchase_qty', 12, 3)->nullable()->change();
            $table->decimal('baseline_quantity', 12, 3)->nullable()->change();
            $table->decimal('scaled_quantity', 12, 3)->nullable()->change();
        });

        DB::table('shopping_list_items')
            ->whereIn('shopping_list_id', DB::table('shopping_lists')->select('id')->where('list_type', 'manual'))
            ->update(['source' => 'manual']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->decimal('qty', 8, 2)->change();
            $table->decimal('purchase_qty', 10, 2)->nullable()->change();
            $table->decimal('baseline_quantity', 12, 2)->nullable()->change();
            $table->decimal('scaled_quantity', 12, 2)->nullable()->change();
            $table->dropColumn(['source', 'included_in_po', 'exclusion_note']);
        });
    }
};
