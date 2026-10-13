<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_configuration_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kind', 16);
            $table->foreignId('report_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin', 16);
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['kind', 'report_template_id', 'created_at'], 'report_config_revisions_lookup_idx');
        });

        $now = now();
        foreach (DB::table('report_templates')->get() as $template) {
            $signatories = json_decode($template->signatories ?? '[]', true);
            DB::table('report_configuration_revisions')->insert([
                'uuid' => (string) Str::uuid(), 'kind' => 'template',
                'report_template_id' => $template->id, 'origin' => 'baseline',
                'snapshot' => json_encode([
                    'type' => $template->type, 'name' => $template->name,
                    'description' => $template->description,
                    'signatories' => is_array($signatories) ? $signatories : [],
                ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach (DB::table('report_branding')->get() as $branding) {
            $snapshot = array_intersect_key((array) $branding, array_flip([
                'hospital_name', 'address', 'accreditation', 'service_name', 'province', 'lgu',
                'logo_left_path', 'logo_right_path', 'logo_left_stored_object_id', 'logo_right_stored_object_id',
            ]));
            DB::table('report_configuration_revisions')->insert([
                'uuid' => (string) Str::uuid(), 'kind' => 'branding', 'origin' => 'baseline',
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_configuration_revisions');
    }
};
