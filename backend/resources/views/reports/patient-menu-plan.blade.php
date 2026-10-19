@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', ['title' => 'NUTRITION INTERVENTION PLAN'])

    <table style="border:0; margin-top:6px;" class="meta">
        <tr>
            <td style="border:0;">Patient: <span class="bold">{{ $patient->display_name ?? '-' }}</span></td>
            <td style="border:0;">Ward: <span class="bold">{{ $patient->ward ?? '-' }}</span></td>
            <td style="border:0;">Plan created: <span class="bold">{{ $intervention_plan->created_at?->copy()->timezone(config('nutriscope-reports.timezone'))->format('M j, Y') ?? '-' }}</span></td>
            <td style="border:0;">Menu week: <span class="bold">{{ optional($meal_plan->week_start_date)->format('M j, Y') ?? '-' }}</span></td>
        </tr>
    </table>

    <div style="margin-top:5px; padding:5px 7px; border:1px solid #d1d5db; background:#f8fafc; font-size:7.5pt;">
        <span class="bold">Daily nutrition prescription:</span>
        Energy: @include('reports.partials.nutrient-prescription', ['limit' => [($prescription['nutrient_operators']['energy_kcal'] ?? 'target') => number_format($prescription['energy_kcal']), 'unit' => 'kcal']]) ·
        @foreach(['protein_g' => 'Protein', 'carbs_g' => 'Carbohydrate', 'fat_g' => 'Fat'] as $key => $label)
            {{ $label }}: @include('reports.partials.nutrient-prescription', ['limit' => [($prescription['nutrient_operators'][$key] ?? 'target') => $prescription[$key], 'unit' => 'g']])@if(!$loop->last) · @endif
        @endforeach
        @foreach($prescription['micronutrient_limits'] as $nutrient => $limit)
            · {{ Illuminate\Support\Str::headline($nutrient) }}:
            @include('reports.partials.nutrient-prescription', ['limit' => $limit])
        @endforeach
        @if(!empty($prescription['weight_target']))
            <div>Target weight: @include('reports.partials.nutrient-prescription', ['limit' => [...$prescription['weight_target'], 'unit' => 'kg']])</div>
        @endif
        <div class="muted" style="margin-top:2px;">Daily prescription: ≤ maximum, ≥ minimum; unmarked amounts are targets. These are not the nutrient totals of this menu.</div>
        <div class="muted" style="margin-top:2px;">
            Fluid prescription: @include('reports.partials.nutrient-prescription', ['limit' => [($prescription['nutrient_operators']['fluid_ml'] ?? 'target') => $fluid_balance['required_fluid_ml'], 'unit' => 'mL/day']]) ·
            Fluid from planned foods: {{ number_format($fluid_balance['food_fluid_ml']) }} mL/day average ·
            Remaining drinking fluid: {{ number_format($fluid_balance['remaining_ml']) }} mL/day.
        </div>
        <div class="muted" style="margin-top:2px;">Estimated fluid from this menu if followed. Remaining drinking fluid follows the same target or limit as the prescription.</div>
        @if($maternal_note)
            <div class="muted" style="margin-top:2px;">{{ $maternal_note }}</div>
        @endif
    </div>

    <div class="bold meal-plan-heading" style="margin-bottom:4px;">Weekly Meal Plan</div>
    <table class="grid menu-grid" style="margin-top:6px;">
        <thead>
            <tr>
                <th style="width:80px;">Meal</th>
                @foreach($days as $day)<th>{{ \Illuminate\Support\Str::substr($day, 0, 3) }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($meals as $meal)
                <tr>
                    <td class="bold">{{ $meal }}</td>
                    @foreach($days as $day)
                        <td>
                            @forelse($grid[$meal][$day] ?? [] as $item)
                                <div>{{ $item['name'] }} <span class="muted">[{{ $item['portion_id'] }}]</span></div>
                            @empty
                                <span class="muted">-</span>
                            @endforelse
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(array_filter($patient_guidance))
        <div class="intervention-guidance" style="margin-top:5px; padding:5px 7px; border:1px solid #d1d5db; font-size:7.5pt;">
            @if($patient_guidance['education'])<div><span class="bold">Education:</span> {{ $patient_guidance['education'] }}</div>@endif
            @if($patient_guidance['counseling'])<div><span class="bold">Counseling:</span> {{ $patient_guidance['counseling'] }}</div>@endif
            @if($patient_guidance['barriers'])<div><span class="bold">Barriers:</span> {{ $patient_guidance['barriers'] }}</div>@endif
            @if($patient_guidance['strategies'])<div><span class="bold">Practical strategies:</span> {{ $patient_guidance['strategies'] }}</div>@endif
        </div>
    @endif

    @if(!empty($portion_details))
        @php
            $formatPortion = static fn ($amount) => (float) $amount > 0 && (float) $amount < 0.1
                ? '<0.1'
                : rtrim(rtrim(number_format((float) $amount, 1, '.', ''), '0'), '.');
        @endphp
        @foreach($portion_pages as $portionPage)
            <div class="portion-page">
                @foreach(array_chunk($portionPage, 4) as $portionRowIndex => $portionRow)
                    @if($portionRowIndex === 0)
                        <div class="portion-intro-block portion-row-block">
                            <table class="portion-row-table portion-heading-table">
                                <tbody>
                                    <tr><td class="portion-heading bold">Portion details</td></tr>
                                </tbody>
                            </table>
                            <p style="font-size:7pt; margin:2px 4px 4px;">Amount to use = listed ingredient amount × your servings ÷ servings the recipe makes.</p>
                    @else
                    <div class="portion-row-block">
                    @endif
                    <table class="portion-row-table">
                        <tbody>
                            <tr class="portion-row">
                                @foreach($portionRow as $portion)
                                    <td class="portion-cell">
                                        <p class="bold" style="font-size:7.5pt; margin:0 0 2px;">{{ $portion['dish'] }}</p>
                                        @if(isset($portion['recipe_yield']))
                                            <p style="font-size:7pt; margin:0 0 2px;">Recipe makes: {{ $formatPortion($portion['recipe_yield']) }} {{ $portion['recipe_yield'] == 1 ? 'serving' : 'servings' }}</p>
                                        @endif
                                        <p style="font-size:7pt; margin:0 0 3px;"><span class="bold">{{ isset($portion['recipe_yield']) ? 'Your servings:' : 'Your portions:' }}</span>
                                            @foreach($portion['variants'] as $variant)
                                                {{ $variant['id'] }} -
                                                @if(isset($variant['patient_servings']))
                                                    {{ $formatPortion($variant['patient_servings']) }} {{ $variant['patient_servings'] == 1 ? 'serving' : 'servings' }}
                                                @else
                                                    {{ $formatPortion($variant['foods'][0]['metric_amount']) }} {{ $variant['foods'][0]['metric_unit'] }}
                                                @endif
                                                {{ $loop->last ? '' : '; ' }}
                                            @endforeach
                                        </p>
                                        @if(isset($portion['recipe_yield']))
                                            <div class="portion-amounts" role="table" aria-label="Recipe ingredients">
                                                <div class="portion-ingredient-row bold" role="row"><span class="portion-ingredient-name" role="columnheader">Ingredient</span><span class="portion-ingredient-amount" role="columnheader">Whole recipe</span></div>
                                                @foreach($portion['foods'] as $food)
                                                    <div class="portion-ingredient-row" role="row"><span class="portion-ingredient-name" role="cell">{{ $food['food'] }}</span><span class="portion-ingredient-amount" role="cell">{{ $formatPortion($food['recipe_quantity']) }} {{ $food['recipe_unit'] }}</span></div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                @for($emptyCell = count($portionRow); $emptyCell < 4; $emptyCell++)
                                    <td class="portion-cell portion-cell-empty"></td>
                                @endfor
                            </tr>
                        </tbody>
                    </table>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif

    @include('reports.partials.signatories')
@endsection
