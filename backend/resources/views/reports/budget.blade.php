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
                [$source === 'consumption' ? 'Actual (Food Served)' : 'Actual (Est. Purchases)', '₱ ' . number_format($summary['actual'], 2)],
                ['Cash Disbursed (POs)', '₱ ' . number_format($cash_flow, 2)],
                // Negative remaining = the allocation was overspent; show it as "Over Allocation" rather than a confusing minus.
                [$remaining >= 0 ? 'Remaining (Cash)' : 'Over Allocation', '₱ ' . number_format(abs($remaining), 2)],
                // Variance = actual − planned: positive = over budget, negative = under.
                ['Variance', ($summary['variance'] > 0 ? 'Over by ₱ ' : ($summary['variance'] < 0 ? 'Under by ₱ ' : '₱ ')) . number_format(abs($summary['variance']), 2)],
            ] as $kpi)
                <td style="border:1px solid #333; padding:8px; width:20%;" class="center">
                    <div class="muted upper" style="font-size:9px;">{{ $kpi[0] }}</div>
                    <div class="bold" style="font-size:14px;">{{ $kpi[1] }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="muted" style="font-size:9px; margin-top:4px;">
        Actual basis: {{ $source === 'consumption' ? 'consumption (value of food served)' : 'estimated from received purchases' }}
        — {{ $days_served }} service day(s) recorded in this period.
        "Remaining" compares the allocation against cash disbursed via purchase orders, a separate axis from food-served value.
    </div>

    @php
        // A2b — server-side SVG bar chart (DomPDF can't run JS charts). Planned vs actual
        // per bucket, scaled to the largest value in the series.
        $trend = $summary['trend'] ?? [];
        $chartW = 520; $chartH = 170; $left = 38; $right = 512; $top = 10; $bottom = 140;
        $maxVal = 1.0;
        foreach ($trend as $t) { $maxVal = max($maxVal, (float) $t['planned'], (float) $t['actual']); }
        $n = max(1, count($trend));
        $slot = ($right - $left) / $n;
        $barW = min(18, $slot * 0.38);
        $yFor = fn ($v) => $bottom - ($v / $maxVal) * ($bottom - $top);
    @endphp

    @if(count($trend) > 0)
        <div class="bold" style="margin-top:12px;">Planned vs Actual (chart)</div>
        <svg width="{{ $chartW }}" height="{{ $chartH + 24 }}" viewBox="0 0 {{ $chartW }} {{ $chartH + 24 }}" style="margin-top:4px;">
            {{-- axes --}}
            <line x1="{{ $left }}" y1="{{ $bottom }}" x2="{{ $right }}" y2="{{ $bottom }}" stroke="#999" stroke-width="0.8" />
            <line x1="{{ $left }}" y1="{{ $top }}" x2="{{ $left }}" y2="{{ $bottom }}" stroke="#999" stroke-width="0.8" />
            {{-- y labels: 0 and max --}}
            <text x="{{ $left - 4 }}" y="{{ $bottom }}" text-anchor="end" font-size="7" fill="#666">0</text>
            <text x="{{ $left - 4 }}" y="{{ $top + 6 }}" text-anchor="end" font-size="7" fill="#666">{{ number_format($maxVal, 0) }}</text>
            @foreach($trend as $i => $t)
                @php
                    $cx = $left + $i * $slot + $slot * 0.12;
                    $pH = $bottom - $yFor((float) $t['planned']);
                    $aH = $bottom - $yFor((float) $t['actual']);
                    $over = (float) $t['actual'] > (float) $t['planned'];
                @endphp
                {{-- planned (grey) --}}
                <rect x="{{ $cx }}" y="{{ $yFor((float) $t['planned']) }}" width="{{ $barW }}" height="{{ max(0, $pH) }}" fill="#cbd5e1" />
                {{-- actual (green within / red over) --}}
                <rect x="{{ $cx + $barW + 2 }}" y="{{ $yFor((float) $t['actual']) }}" width="{{ $barW }}" height="{{ max(0, $aH) }}" fill="{{ $over ? '#dc2626' : '#059669' }}" />
                <text x="{{ $cx + $barW }}" y="{{ $bottom + 9 }}" text-anchor="middle" font-size="6" fill="#666">{{ \Illuminate\Support\Str::limit((string) $t['bucket'], 7, '') }}</text>
            @endforeach
            {{-- legend --}}
            <rect x="{{ $left }}" y="{{ $bottom + 14 }}" width="8" height="8" fill="#cbd5e1" />
            <text x="{{ $left + 12 }}" y="{{ $bottom + 21 }}" font-size="7" fill="#333">Planned</text>
            <rect x="{{ $left + 60 }}" y="{{ $bottom + 14 }}" width="8" height="8" fill="#059669" />
            <text x="{{ $left + 72 }}" y="{{ $bottom + 21 }}" font-size="7" fill="#333">Actual (red = over)</text>
        </svg>
    @endif

    <div class="bold" style="margin-top:12px;">Planned vs Actual</div>
    <table class="grid" style="margin-top:4px;">
        <thead>
            <tr><th>Period</th><th class="right">Planned</th><th class="right">Actual</th><th class="right">Variance (+ over / − under)</th></tr>
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
