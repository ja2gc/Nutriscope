@extends('reports.layout')

@php
    $join = fn ($v) => is_array($v) ? implode(', ', array_filter($v)) : $v;
    $dash = fn ($v) => ($v === null || $v === '') ? '' : $v;
@endphp

@section('body')
    @include('reports.partials.letterhead', [
        'title'    => 'Medical Nutrition Therapy (Nutrition Care Plan)',
        'subtitle' => trim(($patient['name'] ?? 'Patient') . ($record_status ? ' — ' . ucfirst($record_status) : '')),
    ])

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
                <div><span class="bold">Physical Assessment:</span> {{ $assessment?->physical_assessment }}</div>
                <div><span class="bold">Functional Assessment:</span> {{ $assessment?->functional_assessment }}</div>
                <div><span class="bold">Chewing / Swallowing:</span> {{ $assessment?->chewing_swallowing_difficulties }}</div>
                <div><span class="bold">Constipation / Diarrhea:</span> {{ $assessment?->constipation }}{{ $assessment?->diarrhea_notes ? ' / ' . $assessment->diarrhea_notes : '' }}</div>
                <div><span class="bold">Allergies:</span> {{ $join($assessment?->allergies) }}</div>
                <div><span class="bold">Food Intolerance:</span> {{ $assessment?->food_intolerance }}</div>
                <div><span class="bold">Nutrient–Drug Interaction:</span> {{ $assessment?->nutrient_drug_interaction }}</div>
            </td>
            <td style="border:0; width:46%;">
                <table class="grid">
                    <tr><th colspan="4">Anthropometrics</th></tr>
                    <tr>
                        <td><span class="muted">Height</span><br>{{ $assessment?->height }}</td>
                        <td><span class="muted">Weight</span><br>{{ $assessment?->weight }}</td>
                        <td><span class="muted">BMI</span><br>{{ $assessment?->bmi }}</td>
                        <td><span class="muted">Usual Wt.</span><br>{{ $assessment?->usual_weight }}</td>
                    </tr>
                    <tr>
                        <td colspan="2"><span class="muted">Wt. Change</span><br>{{ $assessment?->weight_loss_percentage }}{{ $assessment?->weight_loss_percentage ? '%' : '' }} {{ $assessment?->weight_loss_period }}</td>
                        <td colspan="2"><span class="muted">% IBW</span><br>{{ $assessment?->ibw_percentage }}</td>
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
                                <td>{{ $val }}</td>
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
            <td style="border:0;"><span class="bold">Risk Score:</span> {{ $risk_score }}</td>
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
    <table class="grid">
        <tr>
            <th>Energy (kcal)</th><th>CHO (g)</th><th>CHON (g)</th><th>Fat (g)</th><th>Fluid (mL)</th>
        </tr>
        <tr class="center">
            <td>{{ $intervention?->energy_kcal }}</td>
            <td>{{ $intervention?->carbs_g }}</td>
            <td>{{ $intervention?->protein_g }}</td>
            <td>{{ $intervention?->fat_g }}</td>
            <td>{{ $intervention?->fluid_ml }}</td>
        </tr>
    </table>
    <div style="margin-top:4px;"><span class="bold">Diet / Stage:</span> {{ $intervention?->disease_stage }}</div>
    <div><span class="bold">Nutrition Education:</span> {{ $intervention?->education_notes }}</div>
    <div><span class="bold">Counseling Goals:</span> {{ $intervention?->counseling_goals }}</div>

    {{-- Monitoring & Evaluation (all entries, dated) --}}
    <div class="section">Nutrition Monitoring &amp; Evaluation</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:74px;">Date</th>
                <th style="width:55px;">Weight</th>
                <th style="width:45px;">BMI</th>
                <th>Intake / Tolerance</th>
                <th>Symptoms</th>
                <th>Evaluation / Summary</th>
            </tr>
        </thead>
        <tbody>
            @forelse($monitorings as $m)
                <tr>
                    <td>{{ optional($m->created_at)->format('M j, Y') }}</td>
                    <td class="center">{{ $m->weight }}</td>
                    <td class="center">{{ $m->bmi }}</td>
                    <td>{{ $m->intake_notes }}</td>
                    <td>{{ $m->symptoms }}</td>
                    <td>{{ $m->clinical_summary }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center muted">No monitoring entries yet.</td></tr>
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
@endsection
