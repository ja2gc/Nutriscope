<?php

namespace Database\Seeders;

use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

class ReportTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'type' => 'adime_individual',
                'name' => 'Individual ADIME Report',
                'blade_view' => 'reports.adime_individual',
                'default_filters' => [],
                'available_filters' => ['patient_id', 'ncp_record_id'],
                'description' => 'Detailed single patient Nutrition Care Process summary.',
                'is_active' => true,
            ],
            [
                'type' => 'adime_aggregate',
                'name' => 'Aggregate ADIME Analysis',
                'blade_view' => 'reports.adime_aggregate',
                'default_filters' => [],
                'available_filters' => ['start_date', 'end_date'],
                'description' => 'Aggregate statistical summary of patient NCP outcomes.',
                'is_active' => true,
            ],
            [
                'type' => 'ncp_census',
                'name' => 'NCP Census Report',
                'blade_view' => 'reports.ncp_census',
                'default_filters' => [],
                'available_filters' => ['date'],
                'description' => 'Ward-by-ward diet orders and risk assessment census.',
                'is_active' => true,
            ],
            [
                'type' => 'inventory',
                'name' => 'Inventory Status & Expiry Report',
                'blade_view' => 'reports.inventory',
                'default_filters' => [],
                'available_filters' => ['category'],
                'description' => 'Current food stock levels, expiry warnings, and shortages.',
                'is_active' => true,
            ],
            [
                'type' => 'budget',
                'name' => 'Budget Variance Analysis',
                'blade_view' => 'reports.budget',
                'default_filters' => [],
                'available_filters' => ['start_date', 'end_date'],
                'description' => 'Comparison of planned vs. actual food service expenditure.',
                'is_active' => true,
            ],
            [
                'type' => 'procurement',
                'name' => 'Procurement Summary',
                'blade_view' => 'reports.procurement',
                'default_filters' => [],
                'available_filters' => ['status'],
                'description' => 'Summary of purchasing requests and purchase orders.',
                'is_active' => true,
            ],
            [
                'type' => 'menu_cycle',
                'name' => 'Active Menu Cycle Plan',
                'blade_view' => 'reports.menu_cycle',
                'default_filters' => [],
                'available_filters' => ['menu_cycle_id'],
                'description' => 'Complete breakdown of meals and recipes for the active cycle.',
                'is_active' => true,
            ],
            [
                'type' => 'patient_menu_plan',
                'name' => 'Patient Individual Menu Plan',
                'blade_view' => 'reports.patient_menu_plan',
                'default_filters' => [],
                'available_filters' => ['patient_id'],
                'description' => 'Personalized daily meal service menu order.',
                'is_active' => true,
            ],
            [
                'type' => 'inspection_report',
                'name' => 'Inspection and Acceptance Report (IAR)',
                'blade_view' => 'reports.inspection_report',
                'default_filters' => [],
                'available_filters' => ['inspection_report_id'],
                'description' => 'Extracted delivery inspection and acceptance details.',
                'is_active' => true,
            ],
            [
                'type' => 'marketing_statement',
                'name' => 'Requisition Marketing Statement',
                'blade_view' => 'reports.marketing_statement',
                'default_filters' => [],
                'available_filters' => ['marketing_statement_id'],
                'description' => 'Weekly marketing order summary statement.',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            ReportTemplate::updateOrCreate(
                ['type' => $template['type']],
                $template
            );
        }
    }
}
