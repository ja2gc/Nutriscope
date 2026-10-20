<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiUsageAnalyticsRequest;
use App\Models\AiUsageLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class AiUsageAnalyticsController extends Controller
{
    private const TIMEZONE = 'Asia/Manila';

    public function __invoke(AiUsageAnalyticsRequest $request): JsonResponse
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $view = $request->validated('view', 'month');
        $year = (int) $request->validated('year', $now->year);

        return $view === 'year'
            ? $this->yearResponse($year)
            : $this->monthResponse($year, (int) $request->validated('month', $now->month), $now);
    }

    private function yearResponse(int $year): JsonResponse
    {
        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, self::TIMEZONE);
        $usage = $this->usageByBucket($start, $start->endOfYear(), 'MONTH');
        $points = collect(range(1, 12))
            ->map(function (int $month) use ($usage): array {
                $bucket = $usage[$month] ?? ['tokens_input' => 0, 'tokens_output' => 0, 'tokens' => 0, 'estimated_cost_usd' => 0.0];

                return ['month' => $month, ...$bucket];
            })
            ->all();

        return response()->json([
            'view' => 'year',
            'year' => $year,
            'timezone' => self::TIMEZONE,
            'total_tokens' => array_sum(array_column($points, 'tokens')),
            'total_tokens_input' => array_sum(array_column($points, 'tokens_input')),
            'total_tokens_output' => array_sum(array_column($points, 'tokens_output')),
            'total_estimated_cost_usd' => array_sum(array_column($points, 'estimated_cost_usd')),
            'points' => $points,
        ]);
    }

    private function monthResponse(int $year, int $month, CarbonImmutable $now): JsonResponse
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE);
        $usage = $this->usageByBucket($start, $start->endOfMonth(), 'DAY');
        $points = collect(range(1, $start->daysInMonth))
            ->map(function (int $day) use ($start, $usage): array {
                $date = $start->addDays($day - 1);
                $bucket = $usage[$day] ?? ['tokens_input' => 0, 'tokens_output' => 0, 'tokens' => 0, 'estimated_cost_usd' => 0.0];

                return [
                    'day' => $day,
                    'tokens_input' => $date->isFuture() ? null : $bucket['tokens_input'],
                    'tokens_output' => $date->isFuture() ? null : $bucket['tokens_output'],
                    'tokens' => $date->isFuture() ? null : $bucket['tokens'],
                    'estimated_cost_usd' => $date->isFuture() ? null : $bucket['estimated_cost_usd'],
                ];
            })
            ->all();

        return response()->json([
            'view' => 'month',
            'year' => $year,
            'month' => $month,
            'timezone' => self::TIMEZONE,
            'total_tokens' => array_sum(array_filter(array_column($points, 'tokens'), is_int(...))),
            'total_tokens_input' => array_sum(array_filter(array_column($points, 'tokens_input'), is_int(...))),
            'total_tokens_output' => array_sum(array_filter(array_column($points, 'tokens_output'), is_int(...))),
            'total_estimated_cost_usd' => array_sum(array_column($points, 'estimated_cost_usd')),
            'points' => $points,
        ]);
    }

    private function usageByBucket(CarbonImmutable $start, CarbonImmutable $end, string $datePart): array
    {
        return AiUsageLog::query()
            ->whereBetween('created_at', [$start->utc(), $end->utc()])
            ->selectRaw("{$datePart}(CONVERT_TZ(created_at, '+00:00', '+08:00')) as bucket")
            ->selectRaw('COALESCE(SUM(tokens_input), 0) as tokens_input')
            ->selectRaw('COALESCE(SUM(tokens_output), 0) as tokens_output')
            ->selectRaw('COALESCE(SUM(tokens_total), 0) as tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_usd), 0) as estimated_cost_usd')
            ->groupBy('bucket')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->bucket => [
                'tokens_input' => (int) $row->tokens_input,
                'tokens_output' => (int) $row->tokens_output,
                'tokens' => (int) $row->tokens,
                'estimated_cost_usd' => (float) $row->estimated_cost_usd,
            ]])
            ->all();
    }
}
