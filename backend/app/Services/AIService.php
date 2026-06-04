<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    /**
     * Call Anthropic Claude API to suggest diagnoses based on patient conditions and details.
     */
    public function suggestDiagnoses(array $data): array
    {
        $apiKey = config('services.anthropic.key');
        $model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        try {
            $userPrompt = "Given these patient conditions and clinical data: " . json_encode($data) . "\n\n"
                . "Respond with ONLY this JSON (no prose, no markdown):\n"
                . "{\"suggestions\":[{\"domain\":\"NI\",\"label\":\"Problem statement\",\"etiology\":\"etiology text\","
                . "\"signs\":\"signs and symptoms text\",\"confidence\":0.85,\"reasoning\":\"brief clinical reasoning\",\"priority\":1}]}\n\n"
                . "Provide 2-4 G-NCP standardized nutrition diagnoses. "
                . "domain must be exactly one of: NI (Intake), NC (Clinical), NB (Behavioral-Environmental). "
                . "confidence is a float 0.0-1.0. priority starts at 1 for highest priority.";

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1024,
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
                    'user_id' => auth()->id() ?? User::first()?->id ?? 1,
                    'model' => $model,
                    'tokens_input' => $inputTokens,
                    'tokens_output' => $outputTokens,
                    'tokens_total' => $totalTokens,
                    'endpoint' => 'diagnosis_suggestion',
                ]);

                $text = $body['content'][0]['text'] ?? '';
                $decoded = json_decode($text, true);
                
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
}
