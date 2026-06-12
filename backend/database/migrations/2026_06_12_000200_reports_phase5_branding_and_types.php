<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reports Phase 5: the report engine's editable org config + new report types.
 *
 * - `report_branding`: a single-row table holding the shared header (logo, hospital
 *   name, address, accreditation, service) so the hospital can fix branding without
 *   a code change (single-tenant config, §2.9 — NOT tenancy).
 * - extend `reports.type` with the Phase-5 generators (PPA, menu calendar, dietary
 *   cash book, procurement pack, demographic census).
 * - `report_templates.signatories`: per-type signatory-block defaults; the
 *   "prepared by" name still auto-fills from the logged-in user at generate time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_branding', function (Blueprint $table) {
            $table->id();
            $table->string('hospital_name')->default('ROMANA PANGAN DISTRICT HOSPITAL');
            $table->string('address')->default('San Jose, Floridablanca, Pampanga');
            $table->string('accreditation')->default('PhilHealth Accredited');
            $table->string('service_name')->default('Nutrition and Dietetics Service');
            $table->string('province')->default('Province of Pampanga');
            $table->string('lgu')->nullable();
            $table->string('logo_left_path')->nullable();
            $table->string('logo_right_path')->nullable();
            $table->timestamps();
        });

        DB::table('report_branding')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Extend the reports.type enum with the Phase-5 generators (additive).
        DB::statement("ALTER TABLE reports MODIFY COLUMN type ENUM(
            'adime_individual', 'adime_aggregate', 'ncp_census', 'inventory', 'inventory_report',
            'budget', 'budget_report', 'procurement', 'menu_cycle', 'menu_cycle_report',
            'patient_menu_plan', 'inspection_report', 'marketing_statement', 'marketing_summary',
            'program_project_activity', 'menu_calendar', 'dietary_cash_book',
            'procurement_pack', 'demographic_census'
        ) NOT NULL");

        Schema::table('report_templates', function (Blueprint $table) {
            $table->json('signatories')->nullable()->after('available_filters');
        });
    }

    public function down(): void
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->dropColumn('signatories');
        });

        DB::statement("ALTER TABLE reports MODIFY COLUMN type ENUM(
            'adime_individual', 'adime_aggregate', 'ncp_census', 'inventory', 'inventory_report',
            'budget', 'budget_report', 'procurement', 'menu_cycle', 'menu_cycle_report',
            'patient_menu_plan', 'inspection_report', 'marketing_statement', 'marketing_summary'
        ) NOT NULL");

        Schema::dropIfExists('report_branding');
    }
};
