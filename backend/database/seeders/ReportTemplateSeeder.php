<?php

namespace Database\Seeders;

use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the report catalog: blade view + per-type signatory-block defaults, taken
 * field-for-field from the real government forms (docs/Nutriscope Forms).
 *
 * Signatories are EDITABLE config (Template Edit tab) — the "prepared by"/buyer
 * role is overridden by the logged-in user at generate time. Org values are seeded
 * defaults, not hardcoded into the generators (§2.9).
 */
class ReportTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $rnd      = ['ELAINE JUSTINA L. ABRIOL, RND', 'Nutritionist-Dietitian II'];
        $chief    = ['RACHELL P. GUTIERREZ, MD, MHM', 'Chief of Hospital II'];
        $oicChief = ['ETHEL REYES, MD, CFP', 'OIC-Chief of Hospital II'];
        $admin    = ['MA. CONCEPCION D. LUGTU, MPA', 'Administrative Officer V'];
        $pgso     = ['FRANCIS V. MASLOG', 'OIC-PGSO'];

        $templates = [
            [
                'type' => 'program_project_activity', 'name' => 'Program Project Activity (PPA)',
                'blade_view' => 'reports.program-project-activity',
                'description' => 'Weekly menu + total cost + headcount + inclusive dates.',
                'signatories' => [
                    ['role' => 'prepared_by', 'label' => 'Prepared by:', 'name' => $rnd[0], 'title' => $rnd[1]],
                    ['role' => 'approved_by', 'label' => 'Approved:', 'name' => $chief[0], 'title' => $chief[1]],
                ],
            ],
            [
                'type' => 'menu_calendar', 'name' => 'Weekly Menu Calendar',
                'blade_view' => 'reports.menu-calendar',
                'description' => 'Printable Mon→Sun menu grid for the kitchen.',
                'signatories' => [
                    ['role' => 'prepared_by', 'label' => 'Prepared by:', 'name' => $rnd[0], 'title' => $rnd[1]],
                    ['role' => 'approved_by', 'label' => 'Approved:', 'name' => $chief[0], 'title' => $chief[1]],
                ],
            ],
            [
                'type' => 'dietary_cash_book', 'name' => 'Dietary Cash Book',
                'blade_view' => 'reports.dietary-cash-book',
                'description' => 'Cash disbursement record (running ledger).',
                'signatories' => [
                    ['role' => 'prepared_by', 'label' => 'Accountable Officer:', 'name' => $rnd[0], 'title' => $rnd[1]],
                ],
            ],
            [
                'type' => 'procurement_pack', 'name' => 'Procurement Pack',
                'blade_view' => 'reports.procurement-pack',
                'description' => 'AIR + Statement of Marketing + Summary of Marketing.',
                'signatories' => [],
            ],
            [
                'type' => 'inspection_report', 'name' => 'Acceptance & Inspection Report',
                'blade_view' => 'reports.procurement-pack',
                'description' => 'AIR signatory block.',
                'signatories' => [
                    ['role' => 'inspected_by', 'label' => 'Inspected by:', 'name' => $pgso[0], 'title' => $pgso[1]],
                    ['role' => 'verified_by', 'label' => 'Verified by:', 'name' => $admin[0], 'title' => $admin[1]],
                    ['role' => 'conforme', 'label' => 'Conforme:', 'name' => $rnd[0], 'title' => 'Nutritionist-Dietitian II / End User'],
                    ['role' => 'approved_by', 'label' => 'Approved:', 'name' => $oicChief[0], 'title' => $oicChief[1]],
                ],
            ],
            [
                'type' => 'marketing_statement', 'name' => 'Statement of Marketing Purchased',
                'blade_view' => 'reports.procurement-pack',
                'description' => 'Statement of marketing signatory block.',
                'signatories' => [
                    ['role' => 'buyer', 'label' => 'Buyer:', 'name' => $rnd[0], 'title' => 'Nutritionist-Dietitian II / Buyer'],
                    ['role' => 'verified_by', 'label' => 'Verified as to Quantity/Quality:', 'name' => $admin[0], 'title' => $admin[1]],
                    ['role' => 'examined_by', 'label' => 'Examined and Approved:', 'name' => $oicChief[0], 'title' => $oicChief[1]],
                ],
            ],
            [
                'type' => 'marketing_summary', 'name' => 'Summary of Marketing',
                'blade_view' => 'reports.procurement-pack',
                'description' => 'Summary of marketing signatory block.',
                'signatories' => [
                    ['role' => 'certified_correct', 'label' => 'Certified Correct:', 'name' => $rnd[0], 'title' => 'Nutritionist-Dietitian II'],
                ],
            ],
            [
                'type' => 'demographic_census', 'name' => 'Demographic / Research Census',
                'blade_view' => 'reports.demographic-census',
                'description' => 'Patient counts by age/sex/ward/diagnosis/status/risk, any date range.',
                'signatories' => [
                    ['role' => 'prepared_by', 'label' => 'Prepared by:', 'name' => $rnd[0], 'title' => $rnd[1]],
                    ['role' => 'approved_by', 'label' => 'Approved by:', 'name' => $chief[0], 'title' => $chief[1]],
                ],
            ],
            [
                'type' => 'patient_menu_plan', 'name' => 'Patient Menu Plan',
                'blade_view' => 'reports.patient-menu-plan',
                'description' => 'A patient ADIME meal plan as a weekly calendar.',
                'signatories' => [
                    ['role' => 'prepared_by', 'label' => 'Prepared by:', 'name' => $rnd[0], 'title' => $rnd[1]],
                ],
            ],
            [
                'type' => 'budget_report', 'name' => 'Budget Report',
                'blade_view' => 'reports.budget',
                'description' => 'Planned vs actual, variance, any range.',
                'signatories' => [
                    ['role' => 'prepared_by', 'label' => 'Prepared by:', 'name' => $rnd[0], 'title' => $rnd[1]],
                    ['role' => 'approved_by', 'label' => 'Approved:', 'name' => $chief[0], 'title' => $chief[1]],
                ],
            ],
            [
                'type' => 'inventory_report', 'name' => 'Inventory Report',
                'blade_view' => 'reports.inventory',
                'description' => 'Stock levels, value, low/no-stock.',
                'signatories' => [
                    ['role' => 'prepared_by', 'label' => 'Prepared by:', 'name' => $rnd[0], 'title' => $rnd[1]],
                ],
            ],
        ];

        foreach ($templates as $t) {
            ReportTemplate::updateOrCreate(
                ['type' => $t['type']],
                [
                    'name'        => $t['name'],
                    'blade_view'  => $t['blade_view'],
                    'description' => $t['description'],
                    'signatories' => $t['signatories'],
                    'is_active'   => true,
                ],
            );
        }
    }
}
