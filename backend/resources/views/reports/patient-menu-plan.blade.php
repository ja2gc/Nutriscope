@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', ['title' => 'NUTRITION INTERVENTION PLAN'])

    <table style="border:0; margin-top:6px;" class="meta">
        <tr>
            <td style="border:0;">Patient: <span class="bold">{{ $patient->display_name ?? '-' }}</span></td>
            <td style="border:0;">Ward: <span class="bold">{{ $patient->ward ?? '-' }}</span></td>
            <td style="border:0;">Plan created: <span class="bold">{{ optional($intervention_plan->created_at)->format('M j, Y') ?? '-' }}</span></td>
            <td style="border:0;">Menu week: <span class="bold">{{ optional($meal_plan->week_start_date)->format('M j, Y') ?? '-' }}</span></td>
        </tr>
    </table>

    <div style="margin-top:5px; padding:5px 7px; border:1px solid #d1d5db; background:#f8fafc; font-size:7.5pt;">
        <span class="bold">Daily nutrition prescription:</span>
        Energy: {{ number_format($prescription['energy_kcal']) }} kcal ·
        Protein: {{ number_format($prescription['protein_g']) }} g ·
        Carbohydrate: {{ number_format($prescription['carbs_g']) }} g ·
        Fat: {{ number_format($prescription['fat_g']) }} g ·
        Fluid guidance: {{ number_format($prescription['fluid_ml']) }} mL
        @foreach($prescription['micronutrient_limits'] as $nutrient => $limit)
            · {{ Illuminate\Support\Str::headline($nutrient) }}:
            @if(isset($limit['min']))min {{ number_format($limit['min']) }}@endif
            @if(isset($limit['min'], $limit['max']))-@endif
            @if(isset($limit['max']))max {{ number_format($limit['max']) }}@endif
            {{ $limit['unit'] ?? '' }}
        @endforeach
        <div class="muted" style="margin-top:2px;">Fluid guidance is informational and is not counted as satisfied by foods in this menu.</div>
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
        @foreach($portion_pages as $portionPage)
            <div class="portion-page">
                <table class="portion-row-table portion-heading-table">
                    <tbody>
                        <tr><td class="portion-heading bold">Portion details</td></tr>
                    </tbody>
                </table>
                @foreach(array_chunk($portionPage, 3) as $portionRow)
                    <table class="portion-row-table">
                        <tbody>
                            <tr class="portion-row">
                                @foreach($portionRow as $portion)
                                    <td class="portion-cell">
                                        <p class="bold" style="font-size:7.5pt; margin:0 0 2px;">{{ $portion['dish'] }}</p>
                                        @foreach($portion['variants'] as $variant)
                                            @foreach($variant['foods'] as $food)
                                                <div style="font-size:7.25pt; margin-left:8px;">
                                                    <span class="bold">[{{ $variant['id'] }}]</span>
                                                    {{ $food['food'] }} -
                                                    @if($food['household_measure']){{ $food['household_measure'] }} · @endif
                                                    {{ rtrim(rtrim(number_format($food['metric_amount'], 1, '.', ''), '0'), '.') }} {{ $food['metric_unit'] }}
                                                </div>
                                            @endforeach
                                        @endforeach
                                    </td>
                                @endforeach
                                @for($emptyCell = count($portionRow); $emptyCell < 3; $emptyCell++)
                                    <td class="portion-cell portion-cell-empty"></td>
                                @endfor
                            </tr>
                        </tbody>
                    </table>
                @endforeach
            </div>
        @endforeach
    @endif

    @include('reports.partials.signatories')
@endsection
