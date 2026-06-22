<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAiUsageLimitRequest;
use App\Models\AiUsageLimit;
use App\Models\AiUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AiUsageLimitController extends Controller
{
    public function show(): JsonResponse
    {
        $limits = AiUsageLimit::current();

        $dailyUsed = (int) AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('tokens_total');

        $monthlyUsed = (int) AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('tokens_total');

        return response()->json([
            'data' => [
                'daily_token_limit'   => $limits->daily_token_limit,
                'monthly_token_limit' => $limits->monthly_token_limit,
                'daily_used'          => $dailyUsed,
                'monthly_used'        => $monthlyUsed,
            ],
        ]);
    }

    public function update(UpdateAiUsageLimitRequest $request): JsonResponse
    {
        $limits = AiUsageLimit::current();
        $limits->update($request->validated());

        return response()->json(['data' => $limits->fresh()]);
    }
}
