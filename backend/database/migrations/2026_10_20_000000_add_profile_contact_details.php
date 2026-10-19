<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['patients', 'suppliers'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->json('additional_contacts')->nullable();
                $table->string('preferred_contact_id', 36)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['patients', 'suppliers'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['additional_contacts', 'preferred_contact_id']);
            });
        }
    }
};
