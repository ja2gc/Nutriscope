<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AiReviewAllowance
{
    private static function key(string $feature): string
    {
        if (! in_array($feature, ['diagnosis', 'monitoring'], true)) {
            throw new \InvalidArgumentException('Unknown clinical AI feature.');
        }

        return 'clinical-ai:'.$feature.':'.auth()->id();
    }

    public static function status(string $feature): array
    {
        $key = self::key($feature);
        $seconds = RateLimiter::availableIn($key);
        $used = RateLimiter::attempts($key);

        return ['limit' => 10, 'used' => $used, 'remaining' => max(0, 10 - $used),
            'resets_at' => now()->addSeconds($seconds)->toIso8601String(), 'window_seconds' => 600];
    }

    /** Charge only actual generation attempts, including unsuccessful responses. */
    public static function reserve(string $feature): void
    {
        $key = self::key($feature);
        Cache::lock($key.':lock', 5)->block(1, function () use ($key, $feature): void {
            if (RateLimiter::tooManyAttempts($key, 10)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Ten requests per ten minutes reached. Try again when the allowance resets.',
                    'meta' => ['budget' => self::status($feature)],
                ], 429)->header('Retry-After', RateLimiter::availableIn($key)));
            }
            RateLimiter::hit($key, 600);
        });
    }
}
