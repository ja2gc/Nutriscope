<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('profile_photo_stored_object_id')->nullable()->constrained('stored_objects')->nullOnDelete();
        });
        Schema::table('screening_documents', function (Blueprint $table): void {
            $table->foreignId('stored_object_id')->nullable()->constrained('stored_objects')->nullOnDelete();
            $table->string('file_path')->nullable()->change();
        });
        Schema::table('purchase_order_attachments', function (Blueprint $table): void {
            $table->foreignId('stored_object_id')->nullable()->constrained('stored_objects')->nullOnDelete();
            $table->string('path')->nullable()->change();
        });
        Schema::table('reports', function (Blueprint $table): void {
            $table->foreignId('official_file_stored_object_id')->nullable()->constrained('stored_objects')->nullOnDelete();
        });
        Schema::table('report_branding', function (Blueprint $table): void {
            $table->foreignId('logo_left_stored_object_id')->nullable()->constrained('stored_objects')->nullOnDelete();
            $table->foreignId('logo_right_stored_object_id')->nullable()->constrained('stored_objects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('report_branding', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('logo_left_stored_object_id');
            $table->dropConstrainedForeignId('logo_right_stored_object_id');
        });
        Schema::table('reports', fn (Blueprint $table) => $table->dropConstrainedForeignId('official_file_stored_object_id'));
        Schema::table('purchase_order_attachments', fn (Blueprint $table) => $table->dropConstrainedForeignId('stored_object_id'));
        Schema::table('screening_documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('stored_object_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('profile_photo_stored_object_id'));
    }
};
