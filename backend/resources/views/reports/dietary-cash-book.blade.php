@extends('reports.layout')

@section('body')
    <div class="title">CASH DISBURSEMENT RECORD</div>
    <table style="border:0; margin-bottom:6px;" class="meta">
        <tr>
            <td style="border:0;"><span class="bold">{{ $branding->hospital_name }}</span> <span class="muted">(Agency)</span></td>
            <td style="border:0;" class="right">Period: <span class="bold">{{ $period_label }}</span></td>
        </tr>
        @if($annual_budget)
            <tr>
                <td style="border:0;" colspan="2" class="muted">
                    Annual Budget ({{ $annual_budget['label'] }}):
                    <span class="bold">₱ {{ number_format($annual_budget['allocated'], 2) }}</span>{{ $annual_budget['per_head_year'] ? ' · ₱ ' . number_format($annual_budget['per_head_year'], 2) . '/head/year' : '' }}
                </td>
            </tr>
        @endif
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width:70px;">Date</th>
                <th style="width:90px;">Ref / O.R. No.</th>
                <th>Name of Payee</th>
                <th>Nature of Payment</th>
                <th class="right" style="width:110px;">Cash Adv. / Replenishment</th>
                <th class="right" style="width:90px;">Disbursements</th>
                <th class="right" style="width:90px;">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="totals">
                <td colspan="6">Beginning Balance</td>
                <td class="right">₱ {{ number_format($beginning_balance, 2) }}</td>
            </tr>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r['date'] }}</td>
                    <td>{{ $r['ref'] }}</td>
                    <td>{{ $r['payee'] }}</td>
                    <td>{{ $r['nature'] }}</td>
                    <td class="right">{{ $r['replenishment'] ? number_format($r['replenishment'], 2) : '' }}</td>
                    <td class="right">{{ $r['disbursement'] ? number_format($r['disbursement'], 2) : '' }}</td>
                    <td class="right">{{ number_format($r['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center muted">No disbursements in this period.</td></tr>
            @endforelse
            <tr class="totals">
                <td colspan="4">TOTAL</td>
                <td class="right">₱ {{ number_format($total_replenishment, 2) }}</td>
                <td class="right">₱ {{ number_format($total_disbursement, 2) }}</td>
                <td class="right">₱ {{ number_format($ending_balance, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @include('reports.partials.signatories')
@endsection
