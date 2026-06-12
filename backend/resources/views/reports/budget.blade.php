@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', [
        'title'    => 'BUDGET REPORT',
        'subtitle' => $period_label,
    ])

    <table style="border:0; margin-top:8px;">
        <tr>
            @foreach([
                ['Allocated', '₱ ' . number_format($allocated, 2)],
                ['Actual Spent', '₱ ' . number_format($summary['actual'], 2)],
                ['Remaining', '₱ ' . number_format($remaining, 2)],
                ['Variance %', $summary['variance_pct'] . '%'],
            ] as $kpi)
                <td style="border:1px solid #333; padding:8px; width:25%;" class="center">
                    <div class="muted upper" style="font-size:9px;">{{ $kpi[0] }}</div>
                    <div class="bold" style="font-size:14px;">{{ $kpi[1] }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="bold" style="margin-top:12px;">Planned vs Actual</div>
    <table class="grid" style="margin-top:4px;">
        <thead>
            <tr><th>Period</th><th class="right">Planned</th><th class="right">Actual</th><th class="right">Variance</th></tr>
        </thead>
        <tbody>
            @forelse($summary['trend'] as $row)
                <tr>
                    <td>{{ $row['bucket'] }}</td>
                    <td class="right">{{ number_format($row['planned'], 2) }}</td>
                    <td class="right">{{ number_format($row['actual'], 2) }}</td>
                    <td class="right">{{ number_format($row['variance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="center muted">No logged data.</td></tr>
            @endforelse
            <tr class="totals">
                <td>Total</td>
                <td class="right">{{ number_format($summary['planned'], 2) }}</td>
                <td class="right">{{ number_format($summary['actual'], 2) }}</td>
                <td class="right">{{ number_format($summary['variance'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    @include('reports.partials.signatories')
@endsection
