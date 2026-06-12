@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', [
        'title'    => 'DEMOGRAPHIC / RESEARCH CENSUS',
        'subtitle' => 'Inclusive Dates: ' . $inclusive_label . ' — Total Patients: ' . $census['total'],
    ])

    <div class="bold" style="margin-top:8px;">Patients by Age Group &amp; Sex</div>
    <table class="grid" style="margin-top:4px;">
        <thead>
            <tr>
                <th>Sex</th>
                @foreach($age_groups as $g)<th class="center">{{ $g }}</th>@endforeach
                <th class="center">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach(['M' => 'Male', 'F' => 'Female'] as $key => $label)
                <tr>
                    <td class="bold">{{ $label }}</td>
                    @foreach($age_groups as $g)
                        <td class="center">{{ $census['age_sex'][$g][$key] ?? 0 }}</td>
                    @endforeach
                    <td class="center bold">{{ $census['by_sex'][$key] ?? 0 }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td>Total</td>
                @foreach($age_groups as $g)<td class="center">{{ $census['age_sex'][$g]['total'] ?? 0 }}</td>@endforeach
                <td class="center">{{ $census['total'] }}</td>
            </tr>
        </tbody>
    </table>

    <table style="border:0; margin-top:12px;">
        <tr style="vertical-align:top;">
            <td style="border:0; width:25%; padding-right:8px;">
                @include('reports.partials._breakdown', ['heading' => 'By Ward', 'data' => $census['by_ward']])
            </td>
            <td style="border:0; width:25%; padding-right:8px;">
                @include('reports.partials._breakdown', ['heading' => 'By Diagnosis', 'data' => $census['by_diagnosis']])
            </td>
            <td style="border:0; width:25%; padding-right:8px;">
                @include('reports.partials._breakdown', ['heading' => 'By Nutritional Status', 'data' => $census['by_status']])
            </td>
            <td style="border:0; width:25%;">
                @include('reports.partials._breakdown', ['heading' => 'By Screening Type', 'data' => $census['by_risk']])
            </td>
        </tr>
    </table>

    @include('reports.partials.signatories')
@endsection
