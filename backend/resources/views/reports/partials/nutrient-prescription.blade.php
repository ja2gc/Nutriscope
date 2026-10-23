@php
    $digits = $precision ?? 2;
    $format = static function ($value) use ($digits) {
        $text = number_format((float) $value, $digits, '.', '');
        return $digits > 0 ? rtrim(rtrim($text, '0'), '.') : $text;
    };
@endphp
@if(isset($limit['target'])){{ $format($limit['target']) }}@elseif(isset($limit['min'], $limit['max'])){{ $format($limit['min']) }}–{{ $format($limit['max']) }}@elseif(isset($limit['max']))≤{{ $format($limit['max']) }}@elseif(isset($limit['min']))≥{{ $format($limit['min']) }}@endif {{ filled($limit['unit'] ?? null) ? $limit['unit'] : \App\Services\MonitoringPlanService::micronutrientUnit($nutrient ?? '') }}
