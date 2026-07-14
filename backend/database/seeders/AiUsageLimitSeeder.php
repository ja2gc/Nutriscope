<?php

namespace Database\Seeders;

use App\Models\AiUsageLimit;
use Illuminate\Database\Seeder;

class AiUsageLimitSeeder extends Seeder
{
    public function run(): void
    {
        AiUsageLimit::current()->update([
            'daily_token_limit' => 35_000,
            'monthly_token_limit' => 1_000_000,
            'cost_per_1m_tokens_usd' => 1.92,
        ]);
    }
}
