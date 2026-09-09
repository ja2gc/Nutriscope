@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', ['title' => 'PATIENT MENU PLAN'])

    <table style="border:0; margin-top:6px;" class="meta">
        <tr>
            <td style="border:0;">Patient: <span class="bold">{{ $patient->display_name ?? '—' }}</span></td>
            <td style="border:0;">Ward: <span class="bold">{{ $patient->ward ?? '—' }}</span></td>
            <td style="border:0;">Week of: <span class="bold">{{ optional($plan->week_start_date)->format('M j, Y') ?? '—' }}</span></td>
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
            @if(isset($limit['min'], $limit['max']))–@endif
            @if(isset($limit['max']))max {{ number_format($limit['max']) }}@endif
            {{ $limit['unit'] ?? '' }}
        @endforeach
        <div class="muted" style="margin-top:2px;">Fluid guidance is informational and is not counted as satisfied by foods in this menu.</div>
    </div>

    <table class="grid" style="margin-top:6px;">
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
                                <div>{{ $item['name'] }}<span class="muted"> {{ $item['quantity'] ? rtrim(rtrim((string)$item['quantity'],'0'),'.') : '' }} {{ $item['unit'] }}</span></div>
                            @empty
                                <span class="muted">—</span>
                            @endforelse
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($recipe_details))
        <div style="margin-top:14px;">
            <p class="bold" style="border-bottom:1px solid #e5e7eb; padding-bottom:2px; margin-bottom:8px; font-size:8pt;">Recipe Details</p>
            @foreach($recipe_details as $recipe)
                <div style="margin-bottom:10px; page-break-inside:avoid;">
                    <p class="bold" style="font-size:8pt; margin-bottom:2px;">
                        {{ $recipe['name'] }}
                        @if($recipe['servings'])
                            <span class="muted" style="font-weight:normal;"> — {{ $recipe['servings'] }} serving{{ $recipe['servings'] != 1 ? 's' : '' }}</span>
                        @endif
                    </p>
                    @if($recipe['prep_notes'])
                        <p style="font-style:italic; margin:0 0 4px 10px; color:#555; font-size:7.5pt;">{{ $recipe['prep_notes'] }}</p>
                    @endif
                    @if(!empty($recipe['ingredients']))
                        <table class="grid" style="margin-left:10px; font-size:7.5pt;">
                            <thead>
                                <tr>
                                    <th style="text-align:left;">Ingredient</th>
                                    <th style="width:55px;">Qty</th>
                                    <th style="width:50px;">Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recipe['ingredients'] as $ing)
                                    <tr>
                                        <td>{{ $ing['name'] }}</td>
                                        <td>{{ $ing['quantity'] ? rtrim(rtrim((string)$ing['quantity'],'0'),'.') : '—' }}</td>
                                        <td>{{ $ing['unit'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @include('reports.partials.signatories')
@endsection
