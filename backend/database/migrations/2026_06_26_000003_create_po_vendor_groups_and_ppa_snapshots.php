<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('lifecycle_status', ['open_execution', 'completed', 'archived'])
                ->default('open_execution')
                ->after('status');
            $table->timestamp('converted_at')->nullable()->after('lifecycle_status');
            $table->timestamp('completed_at')->nullable()->after('converted_at');
            $table->timestamp('archived_at')->nullable()->after('completed_at');
            $table->decimal('actual_budget_per_head_per_day', 10, 2)->nullable()->after('total_amount');
            $table->index(['shopping_list_id', 'lifecycle_status']);
        });

        Schema::create('purchase_order_vendor_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('or_number')->nullable();
            $table->enum('status', ['pending', 'received'])->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamp('stocked_at')->nullable();
            $table->timestamps();
            $table->unique(['purchase_order_id', 'supplier_id'], 'po_vg_po_supplier_unique');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('vendor_group_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('purchase_order_vendor_groups')
                ->nullOnDelete();
            $table->index(['purchase_order_id', 'vendor_group_id'], 'po_items_po_group_idx');
        });

        Schema::table('purchase_order_attachments', function (Blueprint $table) {
            $table->foreignId('vendor_group_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('purchase_order_vendor_groups')
                ->nullOnDelete();
            $table->index(['purchase_order_id', 'vendor_group_id', 'type'], 'po_att_po_group_type_idx');
        });

        Schema::create('program_project_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->unique()->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('activity')->default('Food Subsistence for Patients');
            $table->text('menu_snapshot')->nullable();
            $table->string('target_date_range')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('estimated_total_cost', 10, 2)->default(0);
            $table->unsignedInteger('estimated_output_patients')->default(0);
            $table->decimal('actual_total_cost', 10, 2)->nullable();
            $table->unsignedInteger('actual_output_patients')->nullable();
            $table->timestamp('execution_frozen_at')->nullable();
            $table->json('planning_payload')->nullable();
            $table->json('execution_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_project_activities');

        Schema::table('purchase_order_attachments', function (Blueprint $table) {
            $table->dropForeign(['vendor_group_id']);
            $table->dropIndex('po_att_po_group_type_idx');
            $table->dropColumn('vendor_group_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['vendor_group_id']);
            $table->dropIndex('po_items_po_group_idx');
            $table->dropColumn('vendor_group_id');
        });

        Schema::dropIfExists('purchase_order_vendor_groups');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['shopping_list_id', 'lifecycle_status']);
            $table->dropColumn([
                'lifecycle_status',
                'converted_at',
                'completed_at',
                'archived_at',
                'actual_budget_per_head_per_day',
            ]);
        });
    }
};
