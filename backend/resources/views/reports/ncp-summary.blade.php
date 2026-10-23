@extends('reports.layout')

@php
    $join = fn ($v) => is_array($v) ? implode(', ', array_filter($v)) : $v;
    $dash = fn ($v) => ($v === null || $v === '') ? '' : $v;
    $number = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

@section('body')
    <style>
        table.grid th, table.grid td { padding: 4px 5px; }
        .monitoring-table td { line-height: 1.35; }
        table.meta td { padding: 1px 3px; }
        .section { margin: 6px 0 2px; padding: 2px 5px; }
        .sigs { margin-top: 16px; }
        .sig-label { margin-bottom: 30px; }
        .prescription-line { margin: 2px 0; }
    </style>
    @include('reports.partials.letterhead', [
        'title'    => 'Medical Nutrition Therapy (Nutrition Care Plan)',
        'subtitle' => $patient['name'] ?? 'Patient',
    ])

    {{-- RP-01/02 / AD-02: completion banner. An incomplete initial ADI is watermarked
         as a draft so an unfinished plan can't be mistaken for a final clinical record. --}}
    @if(empty($is_complete))
        <div style="border:2px solid #b91c1c; color:#b91c1c; padding:6px 10px; margin:6px 0; font-weight:bold; text-align:center;">
            DRAFT — INCOMPLETE NUTRITION CARE PLAN @if(!empty($incomplete_items)) (missing: {{ implode(', ', $incomplete_items) }}) @endif
        </div>
    @else
        <div style="text-align:right; font-size:10px; color:#555; margin-top:2px;">
            Completion: <span class="bold">{{ $completion_stage }}</span>
        </div>
    @endif

    {{-- Patient header --}}
    <table class="meta" style="margin-top:6px;">
        <tr>
            <td style="border:0; width:50%;"><span class="bold">Name:</span> {{ $patient['name'] }}</td>
            <td style="border:0; width:25%;"><span class="bold">Hosp. No.:</span> {{ $patient['hospital_number'] }}</td>
            <td style="border:0; width:12%;"><span class="bold">Age:</span> {{ $patient['age'] }}</td>
            <td style="border:0; width:13%;"><span class="bold">Sex:</span> {{ $patient['sex'] }}</td>
        </tr>
        <tr>
            <td style="border:0;"><span class="bold">Attending Physician:</span> {{ $patient['physician'] }}</td>
            <td style="border:0;"><span class="bold">Date of Admission:</span> {{ $patient['admission_date'] }}</td>
            <td style="border:0;" colspan="2"><span class="bold">Religion:</span> {{ $patient['religion'] }}</td>
        </tr>
        <tr>
            <td style="border:0;" colspan="4"><span class="bold">Diagnosis:</span> {{ $patient['diagnosis'] }}</td>
        </tr>
    </table>

    {{-- Nutrition Assessment --}}
    <div class="section">Nutrition Assessment</div>
    <table style="border:0;">
        <tr style="vertical-align:top;">
            <td style="border:0; width:54%; padding-right:8px;">
                <div><span class="bold">Present Diet:</span> {{ $assessment?->present_diet }}</div>
                <div><span class="bold">Energy / Nutrient Intake:</span> {{ $assessment?->energy_intake_status }}{{ $assessment?->dietary_intake ? ' — ' . $assessment->dietary_intake : '' }}</div>
                <div><span class="bold">Physical Activity Level (PAL):</span> {{ $assessment?->physical_activity_level }}</div>
                <div><span class="bold">Functional Assessment:</span> {{ $assessment?->functional_assessment }}</div>
                <div><span class="bold">Chewing / Swallowing:</span> {{ $assessment?->chewing_swallowing_difficulties }}{{ $assessment?->chewing_swallowing_details ? ' — ' . $assessment->chewing_swallowing_details : '' }}</div>
                <div><span class="bold">Constipation:</span> {{ $assessment?->constipation }}{{ $assessment?->constipation_details ? ' — ' . $assessment->constipation_details : '' }}</div>
                <div><span class="bold">Diarrhea:</span> {{ $assessment?->diarrhea_notes }}{{ $assessment?->diarrhea_details ? ' — ' . $assessment->diarrhea_details : '' }}</div>
                <div><span class="bold">Allergies:</span> {{ $join($assessment?->allergies) }}</div>
                <div><span class="bold">Food Intolerance:</span> {{ $assessment?->food_intolerance }}</div>
                <div><span class="bold">Nutrient–Drug Interaction:</span> {{ $assessment?->nutrient_drug_interaction }}</div>
            </td>
            <td style="border:0; width:46%;">
                <table class="grid">
                    <tr><th colspan="4">Anthropometrics</th></tr>
                    <tr>
                        <td><span class="muted">Height</span><br>{{ $number($assessment?->height) }}</td>
                        <td><span class="muted">Weight</span><br>{{ $number($assessment?->weight) }}</td>
                        <td><span class="muted">BMI</span><br>{{ $number($assessment?->bmi) }}</td>
                        <td><span class="muted">Usual Wt.</span><br>{{ $number($assessment?->usual_weight) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2"><span class="muted">Wt. Change</span><br>{{ $number($assessment?->weight_loss_percentage) }}{{ $assessment?->weight_loss_percentage ? '%' : '' }} {{ $assessment?->weight_loss_period }}</td>
                        <td colspan="2"><span class="muted">% IBW</span><br>{{ $number($assessment?->ibw_percentage) }}</td>
                    </tr>
                </table>
                <table class="grid" style="margin-top:4px;">
                    <tr><th colspan="4">Biochemical Data</th></tr>
                    @php
                        $labs = [
                            'Albumin' => $biochem?->albumin, 'Hematocrit' => $biochem?->hematocrit,
                            'BUN' => $biochem?->bun, 'Hemoglobin' => $biochem?->hemoglobin,
                            'Calcium' => $biochem?->calcium, 'LDL' => $biochem?->ldl,
                            'Cholesterol' => $biochem?->cholesterol, 'Phosphate' => $biochem?->phosphate,
                            'Creatinine' => $biochem?->creatinine, 'Potassium' => $biochem?->potassium,
                            'Glucose' => $biochem?->glucose, 'Sodium' => $biochem?->sodium,
                            'HbA1c' => $biochem?->hba1c, 'Triglycerides' => $biochem?->triglycerides,
                            'HDL' => $biochem?->hdl, 'URR' => $biochem?->urr,
                        ];
                        $labPairs = array_chunk($labs, 2, true);
                    @endphp
                    @foreach($labPairs as $pair)
                        <tr>
                            @foreach($pair as $name => $val)
                                <td><span class="muted">{{ $name }}</span></td>
                                <td>{{ $number($val) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if($biochem?->bp || $biochem?->abg)
                        <tr><td><span class="muted">BP</span></td><td>{{ $biochem?->bp }}</td><td><span class="muted">ABG</span></td><td>{{ $biochem?->abg }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Status / risk --}}
    <div class="section">Nutritional Status &amp; Risk</div>
    <table class="meta">
        <tr>
            <td style="border:0;"><span class="bold">Nutritional Status:</span> {{ $nutritional_status }}</td>
            <td style="border:0;"><span class="bold">Risk Score:</span> {{ $number($risk_score) }}</td>
            <td style="border:0;"><span class="bold">Risk Level:</span> {{ $risk_band }}</td>
        </tr>
    </table>

    {{-- Diagnosis (PES) --}}
    <div class="section">Nutrition Diagnosis (PES)</div>
    @forelse($diagnoses as $d)
        <div style="margin-bottom:3px;">• {{ $d['pes'] }}{{ $d['domain'] ? ' (' . $d['domain'] . ')' : '' }}</div>
    @empty
        <div class="muted">No diagnosis recorded.</div>
    @endforelse

    {{-- Intervention --}}
    <div class="section">Nutrition Intervention</div>
    <div class="prescription-line">
        @foreach(['energy_kcal' => ['Energy', 'kcal'], 'carbs_g' => ['Carbohydrate', 'g'], 'protein_g' => ['Protein', 'g'], 'fat_g' => ['Fat', 'g'], 'fluid_ml' => ['Fluid', 'mL']] as $key => [$label, $unit])
            @if(!$loop->first) · @endif<span class="bold">{{ $label }}:</span> @if($intervention?->{$key} !== null)@include('reports.partials.nutrient-prescription', ['limit' => [($intervention->nutrient_operators[$key] ?? 'target') => $intervention->{$key}, 'unit' => $unit], 'precision' => 0])@else — {{ $unit }}@endif
        @endforeach
    </div>
    <div class="prescription-line">
    @foreach($intervention?->micronutrient_limits ?? [] as $nutrient => $limit)
        @if(!$loop->first) · @endif<span><span class="bold">{{ Illuminate\Support\Str::headline($nutrient) }}:</span>
            @include('reports.partials.nutrient-prescription', ['limit' => $limit])
        </span>
    @endforeach
    </div>
    @if(!empty($intervention?->weight_target))
        <div class="prescription-line"><span class="bold">Target weight:</span> @include('reports.partials.nutrient-prescription', ['limit' => [...$intervention->weight_target, 'unit' => 'kg']])</div>
    @endif
    @if($intervention)<div class="muted">≤ maximum; ≥ minimum; unmarked amounts are targets.</div>@endif
    <div class="intervention-guidance">
        @foreach(['disease_stage' => 'Diet / Stage', 'education_notes' => 'Nutrition Education', 'counseling_goals' => 'Behavioral Goals', 'barriers' => 'Identified Barriers', 'strategies' => 'Strategies'] as $key => $label)
            @if(filled($intervention?->{$key}))
                <div><span class="bold">{{ $label }}:</span> {{ $intervention->{$key} }}</div>
            @endif
        @endforeach
        {{-- RP-03: reference the patient's meal plan for this cycle. --}}
        @if(!empty($meal_plan))
            <div><span class="bold">Meal Plan:</span> Week of {{ $meal_plan['week_start_date'] }} (#{{ $meal_plan['id'] }}, {{ ucfirst($meal_plan['status']) }})</div>
        @endif
    </div>

    {{-- Monitoring & Evaluation (all entries, dated) --}}
    <div class="section">Nutrition Monitoring &amp; Evaluation</div>
    <table class="grid monitoring-table">
        <thead>
            <tr>
                <th style="width:74px;">Date</th>
                <th style="width:55px;">Weight</th>
                <th style="width:45px;">BMI</th>
                <th style="width:145px;">Intake</th>
                <th>Evaluation / Summary</th>
            </tr>
        </thead>
        <tbody>
            @forelse($monitorings as $m)
                <tr>
                    <td>{{ optional($m->observed_at ?? $m->created_at)->format('M j, Y') }}</td>
                    <td class="center">{{ $number($m->weight) }}</td>
                    <td class="center">{{ $number($m->bmi) }}</td>
                    <td>
                        @php($intake = is_array($m->lab_values) ? $m->lab_values : [])
                        @if(isset($intake['energy_kcal']))
                            Energy: {{ number_format($intake['energy_kcal'], 0, '.', '') }} / @if($intervention?->energy_kcal !== null)@include('reports.partials.nutrient-prescription', ['limit' => [($intervention->nutrient_operators['energy_kcal'] ?? 'target') => $intervention->energy_kcal, 'unit' => 'kcal'], 'precision' => 0])@else — kcal @endif<br>
                        @endif
                        @if(isset($intake['protein_g']))
                            Protein: {{ number_format($intake['protein_g'], 0, '.', '') }} /
                            @if($intervention?->protein_g !== null)
                                @include('reports.partials.nutrient-prescription', ['limit' => [($intervention->nutrient_operators['protein_g'] ?? 'target') => $intervention->protein_g, 'unit' => 'g'], 'precision' => 0])
                            @else
                                — g
                            @endif
                            <br>
                        @endif
                    </td>
                    <td>{{ $m->clinical_summary }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center muted">No monitoring entries yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Supporting Documents — attachments filed against this NCP cycle (rnd.md §3.1) --}}
    @if(($attachments ?? collect())->isNotEmpty())
        <div class="section">Supporting Documents</div>
        <table class="grid">
            <thead>
                <tr>
                    <th>Document</th>
                    <th style="width:90px;">Type</th>
                    <th style="width:110px;">Date Filed</th>
                </tr>
            </thead>
            <tbody>
                @foreach($attachments as $doc)
                    <tr>
                        <td>{{ $doc->original_name ?? basename($doc->file_path) }}</td>
                        <td>{{ $doc->type ?? '—' }}</td>
                        <td>{{ optional($doc->created_at)->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @include('reports.partials.signatories')

    {{-- Appendix: scanned lab sheets / screening forms uploaded for this cycle. --}}
    @if(!empty($attachment_images))
        <div style="page-break-before: always;"></div>
        <div class="section">Appendix — Uploaded Documents</div>
        @foreach($attachment_images as $img)
            <div style="margin-bottom:12px;">
                <p style="font-size:10px; color:#555; margin:0 0 3px;">
                    <span class="bold">{{ $img['name'] }}</span>@if($img['date']) — {{ $img['date'] }}@endif
                </p>
                <img src="{{ $img['path'] }}" style="max-width:100%; max-height:520px; border:1px solid #ddd;">
            </div>
        @endforeach
    @endif
@endsection
