<?php

use App\Models\Diagnosis;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$diagnosis = Diagnosis::query()
    ->where('ai_generated', true)
    ->latest()
    ->first();

if (! $diagnosis) {
    fwrite(STDERR, "No AI-generated diagnosis exists to edit.\n");
    exit(1);
}

$ncpRecord = $diagnosis->ncpRecord()->firstOrFail();
$rnd = User::query()->findOrFail($ncpRecord->rnd_user_id);
$token = $rnd->createToken('ai-diagnosis-edit-smoke')->plainTextToken;

$payload = [
    'domain' => 'NC',
    'problem' => 'Unintended weight loss',
    'etiology' => 'reduced oral intake after RND review',
    'signs_symptoms' => '8% weight loss in 1 month and BMI 17.58 kg/m2',
    'extra_notes' => 'Live smoke edit confirmed AI diagnosis uses the normal edit route.',
];

$request = Request::create(
    "/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/{$diagnosis->id}",
    'PATCH',
    server: [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        'CONTENT_TYPE' => 'application/json',
    ],
    content: json_encode($payload, JSON_THROW_ON_ERROR),
);

$response = $app->handle($request);
$body = json_decode($response->getContent(), true);
$diagnosis->refresh();

echo json_encode([
    'status' => $response->getStatusCode(),
    'response' => $body,
    'database' => [
        'id' => $diagnosis->id,
        'ncp_record_id' => $diagnosis->ncp_record_id,
        'domain' => $diagnosis->domain,
        'problem' => $diagnosis->problem,
        'etiology' => $diagnosis->etiology,
        'signs_symptoms' => $diagnosis->signs_symptoms,
        'pes_statement' => $diagnosis->pes_statement,
        'extra_notes' => $diagnosis->extra_notes,
        'ai_generated' => $diagnosis->ai_generated,
    ],
], JSON_PRETTY_PRINT).PHP_EOL;

$app->terminate($request, $response);
