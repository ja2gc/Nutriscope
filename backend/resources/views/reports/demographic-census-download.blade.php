@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', ['title' => 'DEMOGRAPHIC CENSUS', 'subtitle' => $summary['label']])

    <p style="margin:6px 0 8px; font-size:9pt;"><span class="bold">Total ADIME cycles:</span> {{ number_format($summary['total']) }}</p>

    <div class="section">Age and sex</div>
    <table class="grid" style="table-layout:fixed; font-size:9pt;">
        <thead><tr><th>Sex</th>@foreach($summary['age_groups'] as $group)<th>{{ $group }}</th>@endforeach<th>Total</th></tr></thead>
        <tbody>
            @foreach(['M' => 'Male', 'F' => 'Female'] as $sex => $label)
                <tr style="background:#fff;"><th style="background:#fff;">{{ $label }}</th>@foreach($summary['age_groups'] as $group)<td class="right">{{ $summary['age_sex'][$group][$sex] ?? 0 }}</td>@endforeach<td class="right bold">{{ collect($summary['age_groups'])->sum(fn ($group) => $summary['age_sex'][$group][$sex] ?? 0) }}</td></tr>
            @endforeach
            <tr class="bold" style="background:#fff;"><th style="background:#fff;">Total</th>@foreach($summary['age_groups'] as $group)<td class="right">{{ $summary['age_sex'][$group]['total'] ?? 0 }}</td>@endforeach<td class="right">{{ collect($summary['age_groups'])->sum(fn ($group) => $summary['age_sex'][$group]['total'] ?? 0) }}</td></tr>
        </tbody>
    </table>
    @if($summary['unknown_sex'] > 0)<p class="muted">Unclassified age or sex: {{ $summary['unknown_sex'] }}</p>@endif

    <table style="border:0; margin-top:8px; table-layout:fixed;"><tr>
        <td style="border:0; padding-right:10px; vertical-align:top;">
            <div class="section">By nutrition support</div>
            <table class="grid">@foreach(['oral' => 'Per Orem', 'tube_feeding' => 'Tube Feeding', 'no_food_menu' => 'NPO / TPN'] as $key => $label)<tr><td>{{ $label }}</td><td class="right bold">{{ $summary['by_nutrition_support'][$key] ?? 0 }}</td></tr>@endforeach</table>
            <div class="section" style="margin-top:14px;">By risk level</div>
            <table class="grid">@foreach(['Low', 'Moderate', 'High'] as $label)<tr><td>{{ $label }}</td><td class="right bold">{{ $summary['by_risk'][$label] ?? 0 }}</td></tr>@endforeach</table>
            @if(($summary['by_risk']['Unspecified'] ?? 0) > 0)<p class="muted">Not scored: {{ $summary['by_risk']['Unspecified'] }}</p>@endif
        </td>
        <td style="border:0; padding:0 10px; vertical-align:top;">
            <div class="section">BMI classification</div>
            @foreach(['pediatric' => 'Pediatric', 'adult' => 'Adult'] as $population => $title)
                <p class="bold" style="margin:10px 0 5px;">{{ $title }}</p>
                <table class="grid">@foreach($summary['by_bmi_classification'][$population] as $label => $count)<tr><td>{{ $label }}</td><td class="right bold">{{ $count }}</td></tr>@endforeach</table>
            @endforeach
            @if(($summary['by_nutritional_status']['Unspecified'] ?? 0) > 0)<p class="muted">Not classified: {{ $summary['by_nutritional_status']['Unspecified'] }}</p>@endif
        </td>
        <td style="border:0; padding-left:10px; vertical-align:top;">
            <div class="section">By nutrition care category</div>
            <table class="grid">@foreach($summary['by_primary_diagnosis_category'] as $label => $count)<tr><td>{{ $label }}</td><td class="right bold">{{ $count }}</td></tr>@endforeach</table>
        </td>
    </tr></table>
@endsection
