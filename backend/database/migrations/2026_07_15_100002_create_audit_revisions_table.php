<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->create('audit_revisions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique('audit_revisions_public_id_unique');
                $table->foreignId('activity_id')
                    ->unique('audit_revisions_activity_id_unique')
                    ->constrained(config('activitylog.table_name'))
                    ->cascadeOnDelete();
                $table->string('module', 64);
                $table->string('domain', 32);
                $table->string('subject_type', 191);
                $table->uuid('subject_public_id');
                $table->string('action', 64);
                $table->unsignedSmallInteger('schema_version');
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamp('created_at')->useCurrent();

                $table->index(
                    ['module', 'occurred_at', 'id'],
                    'audit_revisions_module_occurred_id_index',
                );
                $table->index(
                    ['subject_type', 'subject_public_id', 'occurred_at'],
                    'audit_revisions_subject_occurred_index',
                );
            });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->dropIfExists('audit_revisions');
    }
};
