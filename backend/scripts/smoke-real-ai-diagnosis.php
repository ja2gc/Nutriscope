<?php

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$key = (string) config('services.anthropic.key');
if ($key === '') {
    fwrite(STDERR, "ANTHROPIC_API_KEY is not configured.\n");
    exit(1);
}

$rnd = User::query()->where('role', 'RND')->first();
if (! $rnd) {
    $rnd = User::forceCreate([
        'name' => 'AI Smoke RND',
        'email' => 'ai-smoke-rnd+'.Str::lower(Str::random(8)).'@example.test',
        'password' => Hash::make(Str::random(32)),
        'role' => 'RND',
        'is_active' => true,
    ]);
}

Auth::login($rnd);

$suffix = now()->format('YmdHis');
$patient = Patient::forceCreate([
    'name' => 'AI Smoke Test Patient '.$suffix,
    'dob' => now()->subYears(72)->toDateString(),
    'sex' => 'Female',
    'admission_date' => now()->toDateString(),
    'medical_diagnosis' => 'Poor oral intake with recent unintentional weight loss',
    'ward' => 'Smoke Test',
    'status' => 'Active',
]);

$ncpRecord = NcpRecord::forceCreate([
    'patient_id' => $patient->id,
    'rnd_user_id' => $rnd->id,
    'type' => 'new',
    'status' => 'draft',
]);

Assessment::forceCreate([
    'ncp_record_id' => $ncpRecord->id,
    'weight' => 45.0,
    'height' => 160.0,
    'bmi' => 17.58,
    'nutritional_status' => 'Moderate Malnutrition',
    'weight_loss_percentage' => 8.0,
    'weight_loss_period' => '1 month',
    'energy_intake_status' => 'Sub-optimal',
    'appetite_changes' => 'Poor appetite',
    'present_diet' => 'Soft diet, low intake',
    'functional_assessment' => 'Needs assistance',
    'physical_activity_level' => 'sedentary',
]);

$suggestions = app(AIService::class)->suggestDiagnoses([
    'conditions' => [
        'Poor appetite',
        'Unintentional weight loss',
        'Underweight BMI',
    ],
    'ibw_percentage' => 78,
    'patient_age' => 72,
    'patient_sex' => 'Female',
    'nutritional_status' => 'Moderate Malnutrition',
    'weight_kg' => 45.0,
    'height_cm' => 160.0,
    'bmi' => 17.58,
    'weight_loss_percentage' => '8% in 1 month',
    'energy_intake_status' => 'Sub-optimal intake, less than 50% of estimated needs for 7 days',
    'present_diet' => 'Soft diet, low intake',
    'appetite_changes' => 'Poor appetite',
    'functional_assessment' => 'Weakness and fatigue',
]);

if ($suggestions === []) {
    fwrite(STDERR, "AI returned no diagnosis suggestions.\n");
    exit(2);
}

$first = $suggestions[0];
$problem = trim((string) ($first['label'] ?? ''));
$etiology = trim((string) preg_replace('/^related to\s+/i', '', (string) ($first['etiology'] ?? '')));
$signs = trim((string) preg_replace('/^as evidenced by\s+/i', '', (string) ($first['signs'] ?? '')));

if ($problem === '' || $etiology === '' || $signs === '') {
    fwrite(STDERR, "AI suggestion was missing a PES component.\n");
    fwrite(STDERR, json_encode($first, JSON_PRETTY_PRINT)."\n");
    exit(3);
}

$diagnosis = Diagnosis::create([
    'ncp_record_id' => $ncpRecord->id,
    'domain' => $first['domain'] ?? 'NI',
    'problem' => $problem,
    'label' => $problem,
    'etiology' => $etiology,
    'signs_symptoms' => $signs,
    'pes_statement' => Diagnosis::buildPes($problem, $etiology, $signs),
    'ai_generated' => true,
]);

Cache::forget('admin_dashboard');

$usage = \App\Models\AiUsageLog::query()
    ->latest()
    ->first(['id', 'user_id', 'model', 'endpoint', 'tokens_input', 'tokens_output', 'tokens_total', 'created_at']);

$dashboardDaily = \App\Models\AiUsageLog::query()
    ->where('created_at', '>=', now()->subDays(29)->startOfDay())
    ->selectRaw('DATE(created_at) as date, COUNT(*) as calls, COALESCE(SUM(tokens_total), 0) as tokens')
    ->groupByRaw('DATE(created_at)')
    ->orderBy('date')
    ->get()
    ->map(fn ($row) => [
        'date' => (string) $row->date,
        'calls' => (int) $row->calls,
        'tokens' => (int) $row->tokens,
    ])
    ->values();

echo json_encode([
    'patient_id' => $patient->id,
    'ncp_record_id' => $ncpRecord->id,
    'diagnosis_id' => $diagnosis->id,
    'suggestion_count' => count($suggestions),
    'saved_diagnosis' => [
        'domain' => $diagnosis->domain,
        'label' => $diagnosis->label,
        'pes_statement' => $diagnosis->pes_statement,
        'ai_generated' => $diagnosis->ai_generated,
    ],
    'latest_usage_log' => $usage?->toArray(),
    'dashboard_daily_series' => $dashboardDaily,
], JSON_PRETTY_PRINT).PHP_EOL;
