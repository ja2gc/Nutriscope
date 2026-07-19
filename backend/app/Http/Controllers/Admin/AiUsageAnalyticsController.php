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
            ->map(fn (int $month): array => ['month' => $month, 'tokens' => (int) ($usage[$month] ?? 0)])
            ->all();

        return response()->json([
            'view' => 'year',
            'year' => $year,
            'timezone' => self::TIMEZONE,
            'total_tokens' => array_sum(array_column($points, 'tokens')),
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

                return [
                    'day' => $day,
                    'tokens' => $date->isFuture() ? null : (int) ($usage[$day] ?? 0),
                ];
            })
            ->all();

        return response()->json([
            'view' => 'month',
            'year' => $year,
            'month' => $month,
            'timezone' => self::TIMEZONE,
            'total_tokens' => array_sum(array_filter(array_column($points, 'tokens'), is_int(...))),
            'points' => $points,
        ]);
    }

    private function usageByBucket(CarbonImmutable $start, CarbonImmutable $end, string $datePart): array
    {
        return AiUsageLog::query()
            ->whereBetween('created_at', [$start->utc(), $end->utc()])
            ->selectRaw("{$datePart}(CONVERT_TZ(created_at, '+00:00', '+08:00')) as bucket")
            ->selectRaw('COALESCE(SUM(tokens_total), 0) as tokens')
            ->groupBy('bucket')
            ->pluck('tokens', 'bucket')
            ->all();
    }
}
