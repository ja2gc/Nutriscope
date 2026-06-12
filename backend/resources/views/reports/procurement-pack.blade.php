@extends('reports.layout')

@section('body')
    @forelse($packs as $i => $pack)
        {{-- ===== Page 1: Acceptance & Inspection Report ===== --}}
        <div class="title">ACCEPTANCE AND INSPECTION REPORT</div>
        <div class="subtitle">{{ $branding->province }}<br>{{ $branding->lgu ?: 'LGU' }}</div>

        <table style="border:0; margin-bottom:4px;" class="meta">
            <tr>
                <td style="border:0;">Supplier: <span class="bold">{{ $pack['supplier']->name ?? '________' }}</span></td>
                <td style="border:0;">AIR No: {{ $pack['po']->id }}</td>
            </tr>
            <tr>
                <td style="border:0;">P.O. No: {{ $pack['po']->po_number }} &nbsp; Date: {{ $pack['order_date'] }}</td>
                <td style="border:0;">O.R. No: {{ $pack['po']->or_number }}</td>
            </tr>
            <tr>
                <td style="border:0;" colspan="2">Requisition Office/Dept.: {{ $branding->hospital_name }}</td>
            </tr>
        </table>

        <table class="grid">
            <thead>
                <tr><th style="width:50px;">Item No.</th><th style="width:80px;">Unit</th><th>Description</th><th class="right" style="width:80px;">Quantity</th></tr>
            </thead>
            <tbody>
                @foreach($pack['air_items'] as $it)
                    <tr>
                        <td class="center">{{ $it['item_no'] }}</td>
                        <td>{{ $it['unit'] }}</td>
                        <td>{{ $it['description'] }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format($it['quantity'], 2), '0'), '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table style="border:0; margin-top:8px;" class="meta">
            <tr>
                <td style="border:0;">Date Received: {{ $pack['order_date'] }}</td>
                <td style="border:0;">Date Inspected: {{ $pack['order_date'] }}</td>
            </tr>
            <tr><td style="border:0;" colspan="2" class="muted">Inspected and verified and found OK as to quantity and specifications.</td></tr>
        </table>

        @include('reports.partials.signatories', ['signatories' => $air_signatories])

        <div class="page-break"></div>

        {{-- ===== Page 2: Statement of Marketing Purchased ===== --}}
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
                <tr class="totals"><td colspan="4">GRAND TOTAL</td><td class="right">₱ {{ number_format($pack['grand_total'], 2) }}</td></tr>
            </tbody>
        </table>

        <p style="margin-top:8px; font-size:10px;">I hereby certify that I have purchased the above named articles, their quantity and at the price set forth above and that I have paid therefore the total sum of <span class="bold">₱ {{ number_format($pack['grand_total'], 2) }}</span> out of the funds advanced to me for such purpose.</p>

        @include('reports.partials.signatories', ['signatories' => $statement_signatories])

        <div class="page-break"></div>

        {{-- ===== Page 3: Summary of Marketing ===== --}}
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

        @if(! $loop->last)<div class="page-break"></div>@endif
    @empty
        @include('reports.partials.letterhead', ['title' => 'PROCUREMENT PACK'])
        <p class="center muted" style="margin-top:30px;">No purchase orders found for this selection.</p>
    @endforelse
@endsection
