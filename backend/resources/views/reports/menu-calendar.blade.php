@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', [
        'title'    => 'WEEKLY MENU CALENDAR',
        'subtitle' => $cycle->name,
    ])

    <table class="grid" style="margin-top:8px;">
        <thead>
            <tr>
                <th style="width:90px;">Meal</th>
                @foreach($days as $day)
                    <th>{{ $day }}@isset($dates[$day])<div class="muted">{{ $dates[$day] }}</div>@endisset</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($meals as $meal)
                <tr>
                    <td class="bold">{{ $meal }}</td>
                    @foreach($days as $day)
                        <td>
                            @forelse($grid[$meal][$day] ?? [] as $name)
                                <div>{{ $name }}</div>
                            @empty
                                <span class="muted">—</span>
                            @endforelse
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top:10px; border:0;" class="meta">
        <tr>
            <td style="border:0;">Population: <span class="bold">{{ number_format($cycle->population) }}</span></td>
            <td style="border:0;">Weekly cost: <span class="bold">₱ {{ number_format($cost['total_cost'], 2) }}</span></td>
            <td style="border:0;">Cost / head / day: <span class="bold">₱ {{ number_format($cost['cost_per_head'], 2) }}</span></td>
        </tr>
    </table>

    @include('reports.partials.signatories')
@endsection
