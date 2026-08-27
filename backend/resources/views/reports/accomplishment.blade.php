@extends('reports.layout')

@section('body')
    @foreach($staff_sheets as $sheet)
        @include('reports.partials.letterhead', ['title' => 'ACCOMPLISHMENT REPORT'])

        <div class="subtitle" style="margin-top:2px;">
            For the Period: <strong>{{ $period_label }}</strong>
        </div>

        <table style="width:100%; margin:6px 0 4px; border:0;">
            <tr>
                <td style="border:0;">
                    <span class="bold">Name of Employee:</span>
                    <span style="border-bottom:1px solid #333; padding:0 60px 0 4px;">{{ $sheet['user']?->display_name ?? '-' }}</span>
                </td>
                <td style="border:0; text-align:right;"><span class="bold">Position:</span> Food Service Staff</td>
            </tr>
        </table>

        <table class="grid" style="font-size:9px; margin-top:4px;">
            <thead>
                <tr>
                    <th style="width:32%; text-align:left;">Tasks Performed</th>
                    @foreach($days as $date)
                        @php $d = \Carbon\Carbon::parse($date); @endphp
                        <th style="text-align:center; min-width:18px;">
                            {{ $d->format('j') }}<br>
                            <span style="font-size:7px;">{{ $d->format('D') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $taskKey => $taskLabel)
                    <tr>
                        <td>{{ $taskLabel }}</td>
                        @foreach($days as $date)
                            @php $cell = $sheet['task_rows'][$taskKey][$date] ?? ''; @endphp
                            <td style="text-align:center;">
                                @if($cell === 'X')
                                    <span style="font-size:9px; color:#333;">X</span>
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if(count($signatories ?? []))
            @include('reports.partials.signatories')
        @else
            <table class="sigs" style="margin-top:32px;">
                <tr>
                    <td>
                        <div class="sig-label">Prepared by:</div>
                        <div class="sig-name">{{ $sheet['user']?->display_name ?? '' }}</div>
                        <div class="sig-title">Food Service Staff</div>
                    </td>
                    <td>
                        <div class="sig-label">Noted by:</div>
                        <div class="sig-name">&nbsp;</div>
                        <div class="sig-title">RND / Section Head</div>
                    </td>
                    <td>
                        <div class="sig-label">Approved by:</div>
                        <div class="sig-name">&nbsp;</div>
                        <div class="sig-title">Administrative Officer</div>
                    </td>
                </tr>
            </table>
        @endif

        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    @if(count($staff_sheets) === 0)
        <p class="muted" style="text-align:center; margin-top:40px;">
            No accomplishment data found for the selected period.
        </p>
    @endif
@endsection
