<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger of manual changes to a budget's yearly allocation — funds added (e.g. approved
 * top-ups from higher-ups) or deducted (corrections). The base allocated_amount stays
 * intact; the effective allocation is base + Σ(signed adjustments), so historical
 * accuracy is preserved and every change is auditable (who, when, why).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->enum('type', ['addition', 'deduction']);
            $table->decimal('amount', 12, 2); // positive magnitude; sign derived from type
            $table->string('reason_category')->nullable(); // predefined label, or "Other"
            $table->text('reason')->nullable();             // free text (required when "Other")
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['budget_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_adjustments');
    }
};
