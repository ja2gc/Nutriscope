@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', [
        'title'    => 'INVENTORY REPORT',
        'subtitle' => 'Generated ' . $generated_at->format('M j, Y'),
    ])

    <table style="border:0; margin-top:8px;" class="meta">
        <tr>
            <td style="border:0;">Total stock value: <span class="bold">₱ {{ number_format($total_value, 2) }}</span></td>
            <td style="border:0;">Low stock: <span class="bold">{{ $low_count }}</span></td>
            <td style="border:0;">Out of stock: <span class="bold">{{ $no_count }}</span></td>
        </tr>
    </table>

    <table class="grid" style="margin-top:4px;">
        <thead>
            <tr>
                <th>Item</th><th>Type</th><th>Category</th>
                <th class="right">In Stock</th><th class="right">Min</th>
                <th class="center">Status</th><th class="right">Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ ucfirst($r['item_type']) }}</td>
                    <td>{{ $r['category'] }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format($r['quantity'], 2), '0'), '.') }} {{ $r['unit'] }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format($r['threshold'], 2), '0'), '.') }}</td>
                    <td class="center">{{ ['no_stock' => 'OUT', 'low' => 'LOW', 'ok' => 'OK'][$r['status']] }}</td>
                    <td class="right">₱ {{ number_format($r['value'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center muted">No inventory rows.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
