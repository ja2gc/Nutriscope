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
        $dailyUsage = AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('COALESCE(SUM(tokens_input), 0) as tokens_input')
            ->selectRaw('COALESCE(SUM(tokens_output), 0) as tokens_output')
            ->selectRaw('COALESCE(SUM(tokens_total), 0) as tokens_total')
            ->first();

        $monthlyUsage = AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(tokens_input), 0) as tokens_input')
            ->selectRaw('COALESCE(SUM(tokens_output), 0) as tokens_output')
            ->selectRaw('COALESCE(SUM(tokens_total), 0) as tokens_total')
            ->first();

        return [
            'daily_token_limit' => $limits->daily_token_limit,
            'monthly_token_limit' => $limits->monthly_token_limit,
            'input_cost_per_1m_tokens_usd' => $limits->input_cost_per_1m_tokens_usd,
            'output_cost_per_1m_tokens_usd' => $limits->output_cost_per_1m_tokens_usd,
            'daily_used' => (int) $dailyUsage->tokens_total,
            'daily_tokens_input' => (int) $dailyUsage->tokens_input,
            'daily_tokens_output' => (int) $dailyUsage->tokens_output,
            'monthly_used' => (int) $monthlyUsage->tokens_total,
            'monthly_tokens_input' => (int) $monthlyUsage->tokens_input,
            'monthly_tokens_output' => (int) $monthlyUsage->tokens_output,
        ];
    }
}
