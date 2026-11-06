@once
<style>
    .air-form { width:100%; font-size:11px; }
    .air-form .air-meta, .air-form .air-footer { table-layout:fixed; }
    .air-form .air-meta td { padding:3px 3px; vertical-align:top; }
    .air-form .air-line { display:inline-block; border-bottom:1px solid #111; min-width:62px; min-height:12px; line-height:1.2; vertical-align:bottom; }
    .air-form .air-items { margin-top:7px; }
    .air-form .air-items th, .air-form .air-items td { text-align:center; }
    .air-form .air-footer { margin-top:8px; }
    .air-form .air-footer td { vertical-align:top; padding:4px 18px; }
    .air-form .air-footer td:first-child { padding-left:0; }
    .air-form .air-footer td:last-child { padding-right:0; }
    .air-form .air-footer tr { page-break-inside:avoid; }
    .air-form .air-acceptance { page-break-inside:avoid; }
</style>
@endonce
@php
    $airSigs = collect($air_signatories ?? [])->keyBy('role');
@endphp
    <div class="air-form">
        @include('reports.partials.letterhead', ['title' => 'ACCEPTANCE AND INSPECTION REPORT'])
        <table class="air-meta">
            <tr>
                <td style="width:75%;">Supplier: <span class="bold">{{ $pack['supplier']->name ?? '' }}</span></td>
                <td>AIR No. <span class="air-line"></span></td>
            </tr>
            <tr>
                <td>P.O. No. <span class="air-line"></span> Date: <span class="air-line"></span> Invoice <span class="air-line"></span></td>
                <td>Date: <span class="bold">{{ $pack['order_date'] }}</span></td>
            </tr>
            <tr><td colspan="2">Requisition Office/Dept.: {{ $branding->hospital_name }}</td></tr>
        </table>
        <table class="grid air-items">
            <thead><tr><th style="width:50px;">Item No.</th><th style="width:80px;">Unit</th><th>Description</th><th style="width:80px;">Quantity</th></tr></thead>
            <tbody>
                @foreach($pack['air_items'] as $item)
                    <tr><td>{{ $item['item_no'] }}</td><td>{{ $item['unit'] }}</td><td>{{ $item['description'] }}</td><td>{{ rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <div class="air-acceptance">
        <table class="air-footer">
            <tr><td style="width:45%;">Date Received: {{ $pack['order_date'] }}</td><td>Date Inspected: {{ $pack['order_date'] }}</td></tr>
            <tr><td>Complete:</td><td></td></tr>
            <tr><td>Partial:</td><td>Inspected and verified and found OK as to<br>quantity and specifications.</td></tr>
        </table>
        @include('reports.partials.signatories', ['signatories' => array_map(fn ($role) => [
            'label' => $role === 'conforme' ? 'CONFORME:' : '',
            'name' => $airSigs[$role]['name'] ?? '',
            'title' => $airSigs[$role]['title'] ?? '',
        ], ['inspected_by', 'verified_by', 'conforme', 'approved_by'])])
        </div>
    </div>
