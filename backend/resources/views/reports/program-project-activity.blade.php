@extends('reports.layout')

@section('body')
    @include('reports.partials.letterhead', ['title' => 'PROGRAM PROJECT ACTIVITY'])

    <table class="grid" style="margin-top:10px;">
        <thead>
            <tr>
                <th style="width:22%;">Activity</th>
                <th>Menu</th>
                <th style="width:14%;">Total Cost</th>
                <th style="width:14%;">Output</th>
                <th style="width:16%;">Target Date</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="bold">{{ $activity }}</td>
                <td>
                    @foreach($menu_days as $day)
                        <div style="margin-bottom:6px;">
                            <span class="bold">{{ $day['label'] }}</span>
                            @foreach($day['meals'] as $meal)
                                <div>{{ $meal['name'] }}</div>
                            @endforeach
                            @if(count($day['meals']) === 0)
                                <div class="muted">—</div>
                            @endif
                        </div>
                    @endforeach
                </td>
                <td class="center bold">₱ {{ number_format($total_cost, 2) }}</td>
                <td class="center">{{ $output_label }}</td>
                <td class="center">{{ $inclusive_label }}</td>
            </tr>
        </tbody>
    </table>

    @include('reports.partials.signatories')
@endsection
