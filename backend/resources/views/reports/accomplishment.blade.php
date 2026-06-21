@extends('reports.layout')

@section('body')
    {{--
        Accomplishment Report — per-staff pay-period duty sheet (FSS §4).
        One page per staff member; landscape A4.
        Columns = days in range, rows = the 7 fixed tasks.
    --}}

    @foreach($staff_sheets as $sheet)
        @include('reports.partials.letterhead', ['title' => 'ACCOMPLISHMENT REPORT'])

        <div class="subtitle" style="margin-top:2px;">
            For the Period: <strong>{{ $period_label }}</strong>
        </div>

        {{-- Staff name line --}}
        <table style="width:100%; margin:6px 0 4px; border:0;">
            <tr>
                <td style="border:0;">
                    <span class="bold">Name of Employee:</span>
                    <span style="border-bottom:1px solid #333; padding:0 60px 0 4px;">{{ $sheet['user']?->name ?? '—' }}</span>
                </td>
            </tr>
        </table>

        {{--
            Task grid: rows = tasks, cols = calendar days.
            DomPDF handles moderate column counts fine on A4 landscape.
        --}}
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
                            @php $cell = $sheet['task_rows'][$taskKey][$date] ?? '–'; @endphp
                            <td style="text-align:center;">
                                @if($cell === 'off-duty')
                                    <span style="font-size:7px; color:#555;">off-duty</span>
                                @elseif(is_numeric($cell) && $cell !== '–')
                                    {{ $cell }}
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach

                {{-- Totals row: day's combined served population (row 4 sum) --}}
                <tr class="totals">
                    <td><strong>Total Patients Served (All Wards)</strong></td>
                    @foreach($days as $date)
                        <td style="text-align:center;">
                            {{ $daily_population[$date] ?? '—' }}
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>

        {{-- Signature blocks --}}
        @if(count($signatories ?? []))
            @include('reports.partials.signatories')
        @else
            {{-- Fallback three-block layout when no template signatories are configured. --}}
            <table class="sigs" style="margin-top:32px;">
                <tr>
                    <td>
                        <div class="sig-label">Prepared by:</div>
                        <div class="sig-name">{{ $sheet['user']?->name ?? '' }}</div>
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
