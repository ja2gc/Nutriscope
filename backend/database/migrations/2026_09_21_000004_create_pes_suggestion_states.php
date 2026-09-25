<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pes_suggestion_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ncp_record_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('catalog_version', 64);
            $table->json('validated_response');
            $table->json('dismissed_candidate_ids')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['ncp_record_id', 'fingerprint', 'catalog_version'],
                'pes_state_record_fingerprint_catalog_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pes_suggestion_states');
    }
};
