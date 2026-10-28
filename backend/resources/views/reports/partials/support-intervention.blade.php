@include('reports.partials.letterhead', ['title' => 'NUTRITION INTERVENTION PLAN'])
<table class="meta" style="border:0; margin-top:6px;"><tr><td style="border:0;">Patient: <span class="bold">{{ $patient->display_name ?? '—' }}</span></td><td style="border:0;">Nutrition support: <span class="bold">{{ $nutrition_support_label }}</span></td><td style="border:0;">Plan created: {{ $intervention_plan->created_at?->copy()->timezone(config('nutriscope-reports.timezone'))->format('M j, Y') }}</td></tr></table>
<div class="section">Nutrition prescription</div>
<div class="prescription-line">
@foreach(['energy_kcal' => ['Energy', 'kcal'], 'protein_g' => ['Protein', 'g'], 'carbs_g' => ['Carbohydrate', 'g'], 'fat_g' => ['Fat', 'g'], 'fluid_ml' => ['Fluid', 'mL']] as $key => [$label, $unit])
    {{ $label }}: @include('reports.partials.nutrient-prescription', ['limit' => [($prescription['nutrient_operators'][$key] ?? 'target') => $prescription[$key], 'unit' => $unit], 'precision' => 0])@if(!$loop->last) · @endif
@endforeach
@foreach($displayed_nutrients as $key) · {{ Illuminate\Support\Str::headline($key) }}: @if(isset($prescription['micronutrient_limits'][$key])) @include('reports.partials.nutrient-prescription', ['limit' => $prescription['micronutrient_limits'][$key]]) @else No target set @endif @endforeach
</div>
@if($nutrition_support === 'tube_feeding')
    @foreach($formula_items as $item)
        @php($formula = $item['nutrient_snapshot'])
        @php($multiplier = (float) $item['portion_multiplier'])
        @php($factor = (float) $formula['recipe_yield'] * $multiplier)
        <div class="section" style="margin-top:9px;">Formula {{ $loop->iteration }}: {{ $formula['name'] }}</div>
        <p class="bold">Portion details</p>
        <table class="grid" style="font-size:9pt;"><thead><tr><th>Ingredient</th><th>Planned amount</th></tr></thead><tbody>
        @foreach($formula['recipe_original_ingredients'] as $ingredient)
            <tr><td>{{ $ingredient['name'] }}</td><td>{{ rtrim(rtrim(number_format((float) $ingredient['quantity'] * $multiplier, 12, '.', ''), '0'), '.') }} {{ $ingredient['unit'] }}</td></tr>
        @endforeach
        </tbody></table>
        <p><span class="bold">Nutrient profile:</span> Energy {{ number_format($formula['calories'] * $factor, 0) }} kcal · Protein {{ number_format($formula['protein'] * $factor, 1) }} g · Carbohydrate {{ number_format($formula['carbs'] * $factor, 1) }} g · Fat {{ number_format($formula['fat'] * $factor, 1) }} g
        @if(isset($formula['water_g'])) · Water {{ number_format($formula['water_g'] * $factor, 1) }} g @endif
        @foreach($displayed_nutrients as $key) · {{ Illuminate\Support\Str::headline($key) }} @if(isset($formula['micronutrients'][$key])){{ rtrim(rtrim(number_format($formula['micronutrients'][$key] * $factor, 3, '.', ''), '0'), '.') }} {{ App\Services\MonitoringPlanService::micronutrientUnit($key) }}@else Not recorded @endif @endforeach</p>
        @if(filled($formula['prep_notes'] ?? null))<p><span class="bold">Preparation:</span> {{ $formula['prep_notes'] }}</p>@endif
    @endforeach
@endif
@foreach(['education_notes' => 'Education', 'counseling_goals' => 'Counseling goals', 'barriers' => 'Barriers', 'strategies' => 'Strategies'] as $key => $label)
    @if(filled($prescription[$key] ?? null))<p><span class="bold">{{ $label }}:</span> {{ $prescription[$key] }}</p>@endif
@endforeach
