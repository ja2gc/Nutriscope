<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DashboardResource;
use App\Models\AiUsageLog;
use App\Models\Patient;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('admin_dashboard', self::CACHE_TTL_SECONDS, function () {
            return $this->aggregateKpis();
        });

        return (new DashboardResource($data))
            ->response();
    }

    private function aggregateKpis(): array
    {
        return [
            'users' => $this->userStats(),
            'patients' => $this->patientStats(),
            'ai_usage' => $this->aiUsageStats(),
            'audit_logs' => $this->auditLogStats(),
            'reports' => $this->reportStats(),
        ];
    }

    private function userStats(): array
    {
        $byRole = User::query()
            ->select('role', DB::raw('COUNT(*) as count'))
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();

        return [
            'total' => array_sum($byRole),
            'by_role' => [
                'Admin' => $byRole['Admin'] ?? 0,
                'RND' => $byRole['RND'] ?? 0,
                'FSS' => $byRole['FSS'] ?? 0,
            ],
        ];
    }

    private function patientStats(): array
    {
        return [
            'total' => Patient::count(),
        ];
    }

    private function aiUsageStats(): array
    {
        $totals = AiUsageLog::query()
            ->select(
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('COALESCE(SUM(tokens_total), 0) as total_tokens'),
                DB::raw('COALESCE(SUM(tokens_input), 0) as tokens_input'),
                DB::raw('COALESCE(SUM(tokens_output), 0) as tokens_output'),
            )
            ->first();

        // Month-to-date totals — what the "AI Usage" KPI card headlines.
        $month = AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->select(
                DB::raw('COUNT(*) as calls'),
                DB::raw('COALESCE(SUM(tokens_total), 0) as tokens'),
            )
            ->first();

        // Per-day series for the last 30 days — what the chart plots.
        $byEndpoint = AiUsageLog::query()
            ->select('endpoint', DB::raw('COUNT(*) as calls'), DB::raw('SUM(tokens_total) as tokens'))
            ->groupBy('endpoint')
            ->get()
            ->keyBy('endpoint')
            ->map(fn ($row) => [
                'calls' => (int) $row->calls,
                'tokens' => (int) $row->tokens,
            ])
            ->toArray();

        $daily = AiUsageLog::query()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as calls'),
                DB::raw('COALESCE(SUM(tokens_total), 0) as tokens'),
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'calls' => (int) $row->calls,
                'tokens' => (int) $row->tokens,
            ])
            ->toArray();

        return [
            'total_calls' => (int) $totals->total_calls,
            'total_tokens' => (int) $totals->total_tokens,
            'tokens_input' => (int) $totals->tokens_input,
            'tokens_output' => (int) $totals->tokens_output,
            'month_calls' => (int) $month->calls,
            'month_tokens' => (int) $month->tokens,
            'by_endpoint' => $byEndpoint,
            'daily' => $daily,
        ];
    }

    private function auditLogStats(): array
    {
        return [
            'total' => Activity::count(),
            'last_7_days' => Activity::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function reportStats(): array
    {
        return [
            'total' => Report::count(),
        ];
    }
}
