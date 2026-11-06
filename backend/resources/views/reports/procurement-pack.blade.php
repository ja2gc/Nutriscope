@extends('reports.layout')

@section('body')
    @forelse($packs as $i => $pack)
        @if($i > 0)
            <div class="page-start" style="height:1px; font-size:1px; line-height:1px;">&nbsp;</div>
        @endif
        <div class="report-page">
            @if(! $pack['is_final'])
                <p class="center bold" style="color:#9a3412; border:1px solid #fdba74; padding:6px;">DRAFT / INCOMPLETE - receiving is not finished</p>
            @endif
            @include('reports.partials.acceptance-inspection')
        </div>

        {{-- ===== Page 2: Statement of Marketing Purchased ===== --}}
        <div class="report-page page-start">
            @include('reports.partials.letterhead', ['title' => 'STATEMENT OF MARKETING PURCHASED'])
        <table style="border:0; margin-bottom:4px;" class="meta">
            <tr>
                <td style="border:0;">Date: {{ $pack['order_date'] }}</td>
                <td style="border:0;" class="right">Period of Consumption: {{ $pack['summary']['inclusive'] }}</td>
            </tr>
        </table>

        <table class="grid">
            <thead>
                <tr><th style="width:50px;">Qty</th><th style="width:70px;">Unit</th><th>Item</th><th class="right" style="width:80px;">Unit Price</th><th class="right" style="width:90px;">Total Value</th></tr>
            </thead>
            <tbody>
                @foreach($pack['statement_items'] as $it)
                    <tr>
                        <td class="center">{{ rtrim(rtrim(number_format($it['qty'], 2), '0'), '.') }}</td>
                        <td>{{ $it['unit'] }}</td>
                        <td>{{ $it['item'] }}</td>
                        <td class="right">{{ number_format($it['unit_price'], 2) }}</td>
                        <td class="right">{{ number_format($it['total_value'], 2) }}</td>
                    </tr>
                @endforeach
                @if(($pack['receipt_adjustment'] ?? 0) != 0)
                    <tr><td colspan="4">Receipt adjustment</td><td class="right">₱ {{ number_format($pack['receipt_adjustment'], 2) }}</td></tr>
                @endif
                <tr class="totals"><td colspan="4">GRAND TOTAL</td><td class="right">₱ {{ number_format($pack['grand_total'], 2) }}</td></tr>
            </tbody>
        </table>

        <p style="margin-top:8px; font-size:10px;">I hereby certify that I have purchased the above named articles, their quantity and at the price set forth above and that I have paid therefore the total sum of <span class="bold">₱ {{ number_format($pack['grand_total'], 2) }}</span> out of the funds advanced to me for such purpose.</p>

            @php
                $statementSigs = collect($statement_signatories ?? [])->keyBy('role');
            @endphp
            @include('reports.partials.signatories', ['signatories' => [
                null,
                ['label' => '', 'name' => $statementSigs['buyer']['name'] ?? '', 'title' => $statementSigs['buyer']['title'] ?? ''],
                ['label' => 'Verified as to Quantity/Quality and Found Correct :', 'name' => $statementSigs['verified_by']['name'] ?? '', 'title' => $statementSigs['verified_by']['title'] ?? ''],
                ['label' => 'Examined and Approved:', 'name' => $statementSigs['examined_by']['name'] ?? '', 'title' => $statementSigs['examined_by']['title'] ?? ''],
            ]])
        </div>

        {{-- ===== Page 3: Summary of Marketing ===== --}}
        <div class="page-start" style="height:1px; font-size:1px; line-height:1px;">&nbsp;</div>
        <div class="report-page">
            @include('reports.partials.letterhead', ['title' => 'SUMMARY OF MARKETING'])
        <table class="grid" style="margin-top:8px;">
            <thead>
                <tr><th>Date Purchased</th><th>Inclusive Dates</th><th class="right">Amount</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $pack['summary']['date_purchased'] }}</td>
                    <td>{{ $pack['summary']['inclusive'] }}</td>
                    <td class="right">₱ {{ number_format($pack['summary']['amount'], 2) }}</td>
                </tr>
                <tr class="totals"><td colspan="2">TOTAL</td><td class="right">₱ {{ number_format($pack['summary']['amount'], 2) }}</td></tr>
            </tbody>
        </table>

            @include('reports.partials.signatories', ['signatories' => $summary_signatories])
        </div>

        {{-- ===== Appendix: uploaded receipts & proof-of-purchase photos ===== --}}
        @if(! empty($pack['attachments']))
            <div class="report-page page-start">
                @include('reports.partials.letterhead', [
                    'title'    => 'RECEIPTS & PROOF OF PURCHASE',
                    'subtitle' => 'P.O. No: ' . $pack['po']->po_number . ($pack['order_date'] ? ' - ' . $pack['order_date'] : ''),
                ])
                @php
                    $receipts = array_values(array_filter($pack['attachments'], fn ($att) => $att['type'] === 'receipt'));
                    $proofs = array_values(array_filter($pack['attachments'], fn ($att) => $att['type'] === 'proof'));
                    $proofCount = count($proofs);
                    $proofColumns = $proofCount <= 3 ? 1 : ($proofCount <= 6 ? 2 : ($proofCount <= 9 ? 3 : 4));
                    $proofRows = max(1, (int) ceil($proofCount / $proofColumns));
                    $proofCellHeight = (int) floor(625 / $proofRows);
                @endphp
                @if($receipts)
                    <div class="section">Receipts</div>
                    <table class="grid" style="table-layout:fixed;">
                        <tr>
                            @foreach($receipts as $att)
                                <td class="center" style="width:{{ 100 / count($receipts) }}%; height:190px; vertical-align:middle;">
                                    @if($att['caption'])<div class="bold" style="font-size:9px;">{{ $att['caption'] }}</div>@endif
                                    @if($att['src'] && (str_starts_with($att['src'], 'data:image/') || file_exists($att['src'])))
                                        <img src="{{ $att['src'] }}" style="max-width:100%; max-height:165px;">
                                    @else
                                        <span class="muted">[image unavailable]</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </table>
                @endif
                @if($proofs)
                    <div class="section">Proof of Purchase</div>
                    <table class="grid" style="table-layout:fixed;">
                        @foreach(array_chunk($proofs, $proofColumns) as $row)
                            <tr>
                                @foreach($row as $att)
                                    <td class="center" style="width:{{ 100 / $proofColumns }}%; height:{{ $proofCellHeight }}px; vertical-align:middle;">
                                        @if($att['caption'])<div class="bold" style="font-size:9px;">{{ $att['caption'] }}</div>@endif
                                        @if($att['src'] && (str_starts_with($att['src'], 'data:image/') || file_exists($att['src'])))
                                            <img src="{{ $att['src'] }}" style="max-width:100%; max-height:{{ $proofCellHeight - 22 }}px;">
                                        @else
                                            <span class="muted">[image unavailable]</span>
                                        @endif
                                    </td>
                                @endforeach
                                @for($empty = count($row); $empty < $proofColumns; $empty++)
                                    <td style="width:{{ 100 / $proofColumns }}%; height:{{ $proofCellHeight }}px; border:0;"></td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endif
    @empty
        @include('reports.partials.letterhead', ['title' => 'PROCUREMENT PACK'])
        <p class="center muted" style="margin-top:30px;">No purchase orders found for this selection.</p>
    @endforelse
@endsection
