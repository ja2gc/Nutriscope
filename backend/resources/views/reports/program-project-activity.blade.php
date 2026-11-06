@extends('reports.layout')

@section('body')
    @php($is_suggested_food = $is_suggested_food ?? true)
    @php($served_populations = $served_populations ?? [])
    @include('reports.partials.letterhead', ['title' => 'PROGRAM PROJECT ACTIVITY'])

    <table class="grid" style="margin-top:10px;">
        <thead>
            <tr>
                <th style="width:22%;">Activity</th>
                <th>{{ $is_suggested_food ? 'Menu' : 'Items' }}</th>
                <th style="width:14%;">Total Cost</th>
                @if($is_suggested_food)<th style="width:14%;">Output</th>@endif
                <th style="width:16%;">Target Date</th>
            </tr>
        </thead>
        <tbody>
            @if($is_suggested_food)
            @foreach($menu_days as $day)
            <tr>
                @if($loop->first)<td class="bold" rowspan="{{ count($menu_days) }}">{{ $activity }}</td>@endif
                <td>
                    <div class="bold">{{ $day['label'] }}</div>
                    @foreach($day['meals'] as $meal)<div class="center">{{ $meal['name'] }}</div>@endforeach
                </td>
                @if($loop->first)<td class="center bold" rowspan="{{ count($menu_days) }}"><div style="padding-top:12px;">₱ {{ number_format($total_cost, 2) }}</div></td>@endif
                <td class="center"><div style="padding-top:12px;">{{ array_key_exists($day['date'], $served_populations) ? number_format($served_populations[$day['date']]) : '' }}</div></td>
                @if($loop->first)<td class="center" rowspan="{{ count($menu_days) }}"><div style="padding-top:12px;">{{ $inclusive_label }}</div></td>@endif
            </tr>
            @endforeach
            @else
            <tr>
                <td class="bold">{{ $activity }}</td>
                <td>
                    @foreach($purchase_items as $item)
                        <div style="margin-bottom:6px;">{{ $item['description'] }} — {{ rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') }} {{ $item['unit'] }}</div>
                    @endforeach
                </td>
                <td class="center bold">₱ {{ number_format($total_cost, 2) }}</td>
                <td class="center">{{ $inclusive_label }}</td>
            </tr>
            @endif
        </tbody>
        @if($is_suggested_food)
        <tfoot>
            <tr><td colspan="3" class="bold">Total Output</td><td class="center bold">{{ number_format($population) }}</td><td></td></tr>
        </tfoot>
        @endif
    </table>

    @if($is_suggested_food)
    <p class="right bold" style="margin-top:8px;">
        Average cost per head a day:
        @if($average_cost_per_head !== null)₱ {{ number_format($average_cost_per_head, 2) }}@else — @endif
    </p>
    @endif

    @include('reports.partials.signatories')
@endsection
