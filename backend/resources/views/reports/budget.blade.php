@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', [
        'title'    => 'BUDGET REPORT',
        'subtitle' => 'Fiscal Year ' . $fiscal_year,
    ])

    @if(!$budget)
        <div class="center muted" style="margin-top:20px;">No budget allocated for FY {{ $fiscal_year }}.</div>
    @else
        <table style="border:0; margin-top:8px;">
            <tr>
                @foreach([
                    ['Allocated', '₱ ' . number_format($allocated_amount, 2)],
                    ['PO Deductions', '−₱ ' . number_format($total_po_deductions, 2)],
                    ['Manual Additions', '+₱ ' . number_format($total_manual_additions, 2)],
                    ['Manual Deductions', '−₱ ' . number_format($total_manual_deductions, 2)],
                    [$remaining_balance >= 0 ? 'Remaining Balance' : 'Over Allocation', '₱ ' . number_format(abs($remaining_balance), 2)],
                ] as $kpi)
                    <td style="border:1px solid #333; padding:8px; width:20%;" class="center">
                        <div class="muted upper" style="font-size:9px;">{{ $kpi[0] }}</div>
                        <div class="bold" style="font-size:14px; {{ $remaining_balance < 0 && str_contains($kpi[0], 'Over') ? 'color:#dc2626;' : '' }}">{{ $kpi[1] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>

        @if($per_head_day_limit !== null)
            <div class="muted" style="font-size:9px; margin-top:4px;">
                Per-head day limit: ₱{{ number_format($per_head_day_limit, 2) }}
            </div>
        @endif

        <div class="bold" style="margin-top:12px;">Ledger Entries</div>
        <table class="grid" style="margin-top:4px;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="right">Amount</th>
                    <th>Reference / PO</th>
                    <th>Reason</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $e)
                    <tr>
                        <td>{{ $e['created_at'] ? \Carbon\Carbon::parse($e['created_at'])->format('M j, Y') : '—' }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($e['type'])) }}</td>
                        <td class="right" style="{{ $e['signed_amount'] < 0 ? 'color:#dc2626;' : '' }}">
                            {{ $e['signed_amount'] >= 0 ? '' : '−' }}₱{{ number_format(abs($e['signed_amount']), 2) }}
                        </td>
                        <td>{{ $e['po_number'] ?? $e['reference'] ?? '—' }}</td>
                        <td>{{ $e['reason'] ?? '—' }}</td>
                        <td>{{ $e['created_by'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="center muted">No ledger entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @include('reports.partials.signatories')
@endsection
