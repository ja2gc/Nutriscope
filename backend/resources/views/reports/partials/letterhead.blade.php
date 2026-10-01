{{-- Shared branding header. Pass $title (report name) and optional $subtitle. --}}
@php
    $logoL = $branding->logo_left_data_uri ?? ($branding->logo_left_path ? storage_path('app/public/' . $branding->logo_left_path) : null);
    $logoR = $branding->logo_right_data_uri ?? ($branding->logo_right_path ? storage_path('app/public/' . $branding->logo_right_path) : null);
@endphp
<div class="report-letterhead" style="position:relative; min-height:58px;">
    <div style="position:absolute; left:0; top:0; width:70px;">
        @if($logoL && (str_starts_with($logoL, 'data:') || file_exists($logoL)))<img src="{{ $logoL }}" style="width:56px; height:56px; object-fit:contain;">@endif
    </div>
    <div class="center" style="padding:0 75px;">
        <div class="bold" style="font-size:13px;">{{ $branding->hospital_name }}</div>
        <div>{{ $branding->address }}</div>
        <div>{{ $branding->accreditation }}</div>
        <div class="bold" style="margin-top:2px;">{{ $branding->service_name }}</div>
    </div>
    <div class="right" style="position:absolute; right:0; top:0; width:70px;">
        @if($logoR && (str_starts_with($logoR, 'data:') || file_exists($logoR)))<img src="{{ $logoR }}" style="width:56px; height:56px; object-fit:contain;">@endif
    </div>
</div>
@isset($title)
    <div class="title">{{ $title }}</div>
@endisset
@isset($subtitle)
    <div class="subtitle">{{ $subtitle }}</div>
@endisset
