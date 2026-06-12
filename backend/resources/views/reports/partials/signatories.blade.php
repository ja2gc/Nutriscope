{{-- Renders signatory blocks two-per-row. Each: label / (space) / NAME / title. --}}
@php $sigs = $signatories ?? []; @endphp
@if(count($sigs))
    <table class="sigs">
        @foreach(array_chunk($sigs, 2) as $pair)
            <tr>
                @foreach($pair as $sig)
                    <td>
                        <div class="sig-label">{{ $sig['label'] ?? '' }}</div>
                        <div class="sig-name">{{ $sig['name'] ?? '' }}</div>
                        <div class="sig-title">{{ $sig['title'] ?? '' }}</div>
                    </td>
                @endforeach
                @if(count($pair) === 1)<td style="border:0;"></td>@endif
            </tr>
        @endforeach
    </table>
@endif
