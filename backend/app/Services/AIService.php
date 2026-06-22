<?php

namespace App\Services;

use App\Exceptions\TokenLimitExceededException;
use App\Models\AiUsageLimit;
use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    /**
     * Call Anthropic Claude API to suggest diagnoses based on patient conditions and details.
     */
    public function suggestDiagnoses(array $data): array
    {
        $this->assertWithinTokenLimits();

        $apiKey = config('services.anthropic.key');
        $model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        try {
            $userPrompt = "Patient clinical data for G-NCP PES nutrition diagnosis:\n"
                . json_encode($data, JSON_PRETTY_PRINT) . "\n\n"
                . "Generate 1-3 new G-NCP standardized PES nutrition diagnoses.\n\n"
                . "Rules:\n"
                . "- If existing_diagnoses are present, do NOT suggest anything that duplicates or overlaps with them\n"
                . "- If the clinical data does not support any new diagnoses beyond what is already documented, return an empty suggestions array\n"
                . "- abnormal_labs contains only lab values outside normal range with their flag (LOW/HIGH) and actual value — use these as objective Signs & Symptoms evidence, citing the value and flag (e.g. 'albumin 2.8 g/dL [LOW]', 'HbA1c 9.1% [HIGH]')\n"
                . "- If no abnormal_labs are present, rely on anthropometric and clinical data for Signs & Symptoms\n"
                . "- Etiology must reference actual data points: medications, intake status, activity level, medical history\n"
                . "- domain must be exactly one of: NI (Intake), NC (Clinical), NB (Behavioral-Environmental)\n"
                . "- confidence is a float 0.0-1.0\n"
                . "- priority starts at 1 for highest priority\n"
                . "- Cover multiple domains where the data supports it\n\n"
                . "Respond with ONLY this JSON, no prose, no markdown:\n"
                . "{\"suggestions\":[{\"domain\":\"NI\",\"label\":\"Problem statement\",\"etiology\":\"etiology text\","
                . "\"signs\":\"signs and symptoms citing abnormal lab values with flags\",\"confidence\":0.85,"
                . "\"reasoning\":\"clinical reasoning citing data\",\"priority\":1}]}";

            $response = Http::timeout(20)->connectTimeout(5)->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1500,
                'system' => 'You are a clinical nutrition AI specializing in G-NCP (Nutrition Care Process). Always respond with valid JSON only. No prose, no markdown fences, only a raw JSON object.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ]
                ],
            ]);

            if ($response->successful()) {
                $body = $response->json();
                
                // Log AI usage
                $inputTokens = $body['usage']['input_tokens'] ?? 0;
                $outputTokens = $body['usage']['output_tokens'] ?? 0;
                $totalTokens = $inputTokens + $outputTokens;

                AiUsageLog::create([
                    'user_id' => auth()->id(),
                    'model' => $model,
                    'tokens_input' => $inputTokens,
                    'tokens_output' => $outputTokens,
                    'tokens_total' => $totalTokens,
                    'endpoint' => 'diagnosis_suggestion',
                ]);

                $text = $body['content'][0]['text'] ?? '';
                $decoded = json_decode($text, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('AIService suggestDiagnoses: malformed JSON from model', [
                        'error' => json_last_error_msg(),
                        'text'  => substr($text, 0, 500),
                    ]);
                    return [];
                }

                return $decoded['suggestions'] ?? [];
            }

            Log::error('AIService API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('AIService error: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Phase 6.3 — optional, cheap monitoring narrative.
     *
     * Consumes ONLY the compact rule-based delta object from
     * MonitoringSummaryService (not raw NCP text), so the payload stays tiny and
     * the per-use cost is near-zero. Returns a 2-3 sentence clinical
     * interpretation + suggested next action, or null on failure (the caller
     * falls back to the free rule-based summary).
     */
    public function narrateMonitoring(array $summary): ?string
    {
        $this->assertWithinTokenLimits();

        $apiKey = config('services.anthropic.key');
        $model  = config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        try {
            $userPrompt = "Patient monitoring trajectory across visits (JSON): " . json_encode($summary) . "\n\n"
                . "Each indicator lists its values from Visit 1 (assessment baseline) through the latest follow-up, "
                . "with its reference range/target and latest status. "
                . "Based on the TRENDS across visits (not just the latest value), write a concise course of action for the dietitian: 2-4 sentences. "
                . "Cite specific indicator trends by name and value. "
                . "Reference the relevant PES problem(s) from pes_statements being addressed. "
                . "End with one concrete next action. "
                . "Use ONLY the indicators provided — never invent values or labs. "
                . "Plain prose, no JSON, no markdown, no preamble.";

            $response = Http::timeout(20)->connectTimeout(5)->withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => $model,
                'max_tokens' => 320,
                'system'     => 'You are a clinical nutrition assistant interpreting monitoring data in the G-NCP framework. Be precise, brief, and actionable. Never invent values not present in the data.',
                'messages'   => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

            if ($response->successful()) {
                $body = $response->json();

                AiUsageLog::create([
                    'user_id'       => auth()->id(),
                    'model'         => $model,
                    'tokens_input'  => $body['usage']['input_tokens']  ?? 0,
                    'tokens_output' => $body['usage']['output_tokens'] ?? 0,
                    'tokens_total'  => ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0),
                    'endpoint'      => 'monitoring_narrative',
                ]);

                return trim($body['content'][0]['text'] ?? '') ?: null;
            }

            Log::error('AIService monitoring narrative failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('AIService narrateMonitoring error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Throws TokenLimitExceededException (renders as 429) if either the
     * daily or monthly token cap is set and current usage has reached it.
     * Must be called before every LLM HTTP request.
     */
    private function assertWithinTokenLimits(): void
    {
        $limits = AiUsageLimit::current();

        if ($limits->daily_token_limit !== null) {
            $dailyUsed = (int) AiUsageLog::query()
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('tokens_total');

            if ($dailyUsed >= $limits->daily_token_limit) {
                throw new TokenLimitExceededException(
                    "Daily AI token limit of {$limits->daily_token_limit} has been reached (used: {$dailyUsed}). Try again tomorrow."
                );
            }
        }

        if ($limits->monthly_token_limit !== null) {
            $monthlyUsed = (int) AiUsageLog::query()
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('tokens_total');

            if ($monthlyUsed >= $limits->monthly_token_limit) {
                throw new TokenLimitExceededException(
                    "Monthly AI token limit of {$limits->monthly_token_limit} has been reached (used: {$monthlyUsed}). Try again next month."
                );
            }
        }
    }
}
