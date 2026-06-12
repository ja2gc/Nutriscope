@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', ['title' => 'PATIENT MENU PLAN'])

    <table style="border:0; margin-top:6px;" class="meta">
        <tr>
            <td style="border:0;">Patient: <span class="bold">{{ $patient->name ?? '—' }}</span></td>
            <td style="border:0;">Ward: <span class="bold">{{ $patient->ward ?? '—' }}</span></td>
            <td style="border:0;">Week of: <span class="bold">{{ optional($plan->week_start_date)->format('M j, Y') ?? '—' }}</span></td>
        </tr>
    </table>

    <table class="grid" style="margin-top:6px;">
        <thead>
            <tr>
                <th style="width:80px;">Meal</th>
                @foreach($days as $day)<th>{{ \Illuminate\Support\Str::substr($day, 0, 3) }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($meals as $meal)
                <tr>
                    <td class="bold">{{ $meal }}</td>
                    @foreach($days as $day)
                        <td>
                            @forelse($grid[$meal][$day] ?? [] as $item)
                                <div>{{ $item['name'] }}<span class="muted"> {{ $item['quantity'] ? rtrim(rtrim((string)$item['quantity'],'0'),'.') : '' }} {{ $item['unit'] }}</span></div>
                            @empty
                                <span class="muted">—</span>
                            @endforelse
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('reports.partials.signatories')
@endsection
