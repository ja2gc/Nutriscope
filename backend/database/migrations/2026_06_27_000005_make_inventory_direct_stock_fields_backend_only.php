<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No structural changes. Inventory stock fields remain backend-only.
        // Remove direct UI/API mutation by guarding routes and removing from request validation.
    }

    public function down(): void
    {
        // No changes to reverse.
    }
};
