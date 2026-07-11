<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAiUsageLimitRequest;
use App\Models\AiUsageLimit;
use App\Models\AiUsageLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class AiUsageLimitController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function show(): JsonResponse
    {
        $limits = AiUsageLimit::current();

        return response()->json(['data' => $this->payload($limits)]);
    }

    public function update(UpdateAiUsageLimitRequest $request): JsonResponse
    {
        $limits = AiUsageLimit::current();
        $data = $request->validated();
        $this->audited(function () use ($limits, $data): void {
            $limits->update($data);
            if ($limits->getChanges() !== []) {
                $this->auditLogger->recordMutation(AuditAction::SettingsChanged, AuditDomain::System, $limits, array_keys($limits->getChanges()));
            }
        });

        return response()->json(['data' => $this->payload($limits->fresh())]);
    }

    private function payload(AiUsageLimit $limits): array
    {
        $dailyUsed = (int) AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('tokens_total');

        $monthlyUsed = (int) AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('tokens_total');

        return [
            'daily_token_limit' => $limits->daily_token_limit,
            'monthly_token_limit' => $limits->monthly_token_limit,
            'cost_per_1m_tokens_usd' => $limits->cost_per_1m_tokens_usd,
            'daily_used' => $dailyUsed,
            'monthly_used' => $monthlyUsed,
        ];
    }
}
