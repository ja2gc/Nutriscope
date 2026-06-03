<?php

namespace Database\Seeders;

use App\Models\ExtractionTemplate;
use Illuminate\Database\Seeder;

class ExtractionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'document_type' => 'screening_adult',
                'field_mappings' => [
                    'weight' => 'weight\s*:\s*([\d\.]+)',
                    'height' => 'height\s*:\s*([\d\.]+)',
                    'food_intake' => 'intake\s*:\s*(.*)',
                ],
                'validation_rules' => [
                    'weight' => 'required|numeric',
                    'height' => 'required|numeric',
                ],
                'version' => '1.0.0',
                'is_active' => true,
            ],
            [
                'document_type' => 'screening_pediatric',
                'field_mappings' => [
                    'weight' => 'weight\s*:\s*([\d\.]+)',
                    'height' => 'height\s*:\s*([\d\.]+)',
                    'age_months' => 'age\s*:\s*(\d+)\s*months',
                ],
                'validation_rules' => [
                    'weight' => 'required|numeric',
                    'height' => 'required|numeric',
                ],
                'version' => '1.0.0',
                'is_active' => true,
            ],
            [
                'document_type' => 'lab_result',
                'field_mappings' => [
                    'albumin' => 'albumin\s*:\s*([\d\.]+)',
                    'hemoglobin' => 'hemoglobin\s*:\s*([\d\.]+)',
                    'glucose' => 'glucose\s*:\s*([\d\.]+)',
                ],
                'validation_rules' => [
                    'albumin' => 'numeric',
                    'hemoglobin' => 'numeric',
                ],
                'version' => '1.0.0',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            ExtractionTemplate::updateOrCreate(
                ['document_type' => $template['document_type']],
                $template
            );
        }
    }
}
