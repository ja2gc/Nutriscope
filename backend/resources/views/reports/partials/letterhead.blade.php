{{-- Shared branding header. Pass $title (report name) and optional $subtitle. --}}
@php
    $logoL = $branding->logo_left_path ? storage_path('app/public/' . $branding->logo_left_path) : null;
    $logoR = $branding->logo_right_path ? storage_path('app/public/' . $branding->logo_right_path) : null;
@endphp
<table style="width:100%; border:0;">
    <tr>
        <td style="width:70px; border:0;">
            @if($logoL && file_exists($logoL))<img src="{{ $logoL }}" style="height:56px;">@endif
        </td>
        <td style="border:0;" class="center">
            <div class="bold" style="font-size:13px;">{{ $branding->hospital_name }}</div>
            <div>{{ $branding->address }}</div>
            <div>{{ $branding->accreditation }}</div>
            <div class="bold" style="margin-top:2px;">{{ $branding->service_name }}</div>
        </td>
        <td style="width:70px; border:0;" class="right">
            @if($logoR && file_exists($logoR))<img src="{{ $logoR }}" style="height:56px;">@endif
        </td>
    </tr>
</table>
@isset($title)
    <div class="title">{{ $title }}</div>
@endisset
@isset($subtitle)
    <div class="subtitle">{{ $subtitle }}</div>
@endisset
