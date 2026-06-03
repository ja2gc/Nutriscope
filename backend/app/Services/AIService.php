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
        $model = config('services.anthropic.model', 'claude-haiku-20240307');

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Based on the following conditions and clinical data, please suggest PES nutrition diagnoses: ' . json_encode($data),
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
