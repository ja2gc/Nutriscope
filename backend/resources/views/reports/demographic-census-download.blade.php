@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', ['title' => 'DEMOGRAPHIC CENSUS', 'subtitle' => $summary['label']])

    <table style="border:0; margin:6px 0 8px;">
        <tr>
            <td style="border:0; font-size:9pt;"><span class="bold">Total ADIME cycles:</span> {{ number_format($summary['total']) }}</td>
            <td style="border:0; font-size:9pt;" class="right">{{ $summary['status'] === 'frozen' ? 'Completed snapshot' : 'Includes current data' }}</td>
        </tr>
    </table>

    <div class="section">Age and sex</div>
    <table class="grid" style="table-layout:fixed; font-size:9pt;">
        <thead><tr><th>Sex</th>@foreach($summary['age_groups'] as $group)<th>{{ $group }}</th>@endforeach<th>Total</th></tr></thead>
        <tbody>
            @foreach(['M' => 'Male', 'F' => 'Female'] as $sex => $label)
                <tr><th>{{ $label }}</th>@foreach($summary['age_groups'] as $group)<td class="right">{{ $summary['age_sex'][$group][$sex] ?? 0 }}</td>@endforeach<td class="right bold">{{ collect($summary['age_groups'])->sum(fn ($group) => $summary['age_sex'][$group][$sex] ?? 0) }}</td></tr>
            @endforeach
            <tr class="totals"><th>Total</th>@foreach($summary['age_groups'] as $group)<td class="right">{{ $summary['age_sex'][$group]['total'] ?? 0 }}</td>@endforeach<td class="right">{{ collect($summary['age_groups'])->sum(fn ($group) => $summary['age_sex'][$group]['total'] ?? 0) }}</td></tr>
        </tbody>
    </table>
    @if($summary['unknown_sex'] > 0)<p class="muted">Unclassified age or sex: {{ $summary['unknown_sex'] }}</p>@endif

    <table style="border:0; margin-top:8px; table-layout:fixed;"><tr>
        <td style="border:0; padding-right:5px; vertical-align:top;">
            <div class="section">By risk level</div>
            <table class="grid">@foreach($summary['by_risk'] as $label => $count)<tr><td>{{ $label }}</td><td class="right bold">{{ $count }}</td></tr>@endforeach</table>
        </td>
        <td style="border:0; padding:0 5px; vertical-align:top;">
            <div class="section">By nutritional status</div>
            <table class="grid">@foreach($summary['by_nutritional_status'] as $label => $count)<tr><td>{{ $label }}</td><td class="right bold">{{ $count }}</td></tr>@endforeach</table>
        </td>
        <td style="border:0; padding-left:5px; vertical-align:top;">
            <div class="section">By nutrition care category</div>
            <table class="grid">@forelse($summary['by_primary_diagnosis_category'] as $label => $count)<tr><td>{{ $label }}</td><td class="right bold">{{ $count }}</td></tr>@empty<tr><td>No cycles in selected period.</td></tr>@endforelse</table>
        </td>
    </tr></table>
@endsection
