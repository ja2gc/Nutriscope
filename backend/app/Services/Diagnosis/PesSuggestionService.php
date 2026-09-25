<?php

namespace App\Services\Diagnosis;

use App\Models\NcpRecord;
use App\Models\PesSuggestionState;
use App\Services\AIService;
use Illuminate\Support\Facades\DB;

class PesSuggestionService
{
    public const EMPTY_MESSAGE = 'No sufficiently supported PES draft was found.';

    public function __construct(
        private PesEvidenceBuilder $evidenceBuilder,
        private PesRuleCatalog $catalog,
        private PesEligibilityService $eligibility,
        private AIService $aiService,
    ) {}

    public function suggest(
        NcpRecord $ncpRecord,
        ?string $dismissedCandidateId = null,
    ): array {
        $evidence = $this->evidenceBuilder->build($ncpRecord);
        $fingerprint = $this->fingerprint($evidence);
        $state = PesSuggestionState::query()
            ->where('ncp_record_id', $ncpRecord->id)
            ->where('fingerprint', $fingerprint)
            ->where('catalog_version', $this->catalog->version())
            ->first();

        if ($state !== null) {
            if ($dismissedCandidateId !== null) {
                $state = $this->dismiss($state, $dismissedCandidateId);
            }

            return $this->response($state, cached: true);
        }

        $eligible = $this->eligibility->eligible($evidence);
        if ($eligible === []) {
            $state = PesSuggestionState::create([
                'ncp_record_id' => $ncpRecord->id,
                'fingerprint' => $fingerprint,
                'catalog_version' => $this->catalog->version(),
                'validated_response' => [],
                'dismissed_candidate_ids' => [],
                'provider_metadata' => ['provider' => 'none', 'reason' => 'no_eligible_candidates'],
            ]);

            return $this->response($state, cached: false);
        }

        $providerCandidates = array_map(static fn (array $candidate): array => [
            'candidate_id' => $candidate['candidate_id'],
            'domain' => $candidate['domain'],
            'problem_key' => $candidate['problem_key'],
            'evidence' => $candidate['evidence'],
            'source_id' => $candidate['source']['id'],
        ], $eligible);

        $raw = $this->aiService->draftPes([
            'catalog_version' => $this->catalog->version(),
            'candidates' => $providerCandidates,
        ]);
        $validated = $this->validateProviderResponse($raw, $eligible);

        $state = PesSuggestionState::create([
            'ncp_record_id' => $ncpRecord->id,
            'fingerprint' => $fingerprint,
            'catalog_version' => $this->catalog->version(),
            'validated_response' => $validated,
            'dismissed_candidate_ids' => [],
            'provider_metadata' => [
                'provider' => 'anthropic',
                'model' => config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
            ],
        ]);

        return $this->response($state, cached: false);
    }

    private function dismiss(PesSuggestionState $state, string $candidateId): PesSuggestionState
    {
        $known = collect($state->validated_response)
            ->pluck('candidate_id')
            ->containsStrict($candidateId);
        if (! $known) {
            throw new \InvalidArgumentException('The selected PES draft is not available.');
        }

        return DB::transaction(function () use ($state, $candidateId): PesSuggestionState {
            $locked = PesSuggestionState::query()->lockForUpdate()->findOrFail($state->id);
            $dismissed = collect($locked->dismissed_candidate_ids ?? [])
                ->push($candidateId)
                ->unique()
                ->values()
                ->all();
            $locked->update(['dismissed_candidate_ids' => $dismissed]);

            return $locked->fresh();
        });
    }

    private function validateProviderResponse(array $raw, array $eligible): array
    {
        if (count($raw) > 3) {
            throw new \RuntimeException('The AI returned too many PES drafts.');
        }

        $byId = collect($eligible)->keyBy('candidate_id');
        $seen = [];
        $validated = [];

        foreach ($raw as $draft) {
            if (! is_array($draft)) {
                throw new \RuntimeException('The AI returned an unexpected PES draft.');
            }

            $candidateId = $draft['candidate_id'] ?? null;
            if (! is_string($candidateId) || isset($seen[$candidateId]) || ! $byId->has($candidateId)) {
                throw new \RuntimeException('The AI returned an unsupported or duplicate PES draft.');
            }
            $seen[$candidateId] = true;
            $candidate = $byId->get($candidateId);

            if (
                ($draft['domain'] ?? null) !== $candidate['domain']
                || ($draft['problem_key'] ?? null) !== $candidate['problem_key']
                || ($draft['source_id'] ?? null) !== $candidate['source']['id']
            ) {
                throw new \RuntimeException('The AI returned an unsupported PES problem or source.');
            }

            $evidenceUsed = $draft['evidence_used'] ?? null;
            if (! is_array($evidenceUsed) || $evidenceUsed === []) {
                throw new \RuntimeException('The AI returned a PES draft without evidence.');
            }
            foreach ($evidenceUsed as $key) {
                if (! is_string($key) || ! array_key_exists($key, $candidate['evidence'])) {
                    throw new \RuntimeException('The AI returned evidence that was not supplied.');
                }
            }

            $etiology = $this->component($draft['etiology'] ?? null, 'etiology');
            $signs = $this->component($draft['signs'] ?? null, 'signs');

            $validated[] = [
                'candidate_id' => $candidateId,
                'domain' => $candidate['domain'],
                'label' => $candidate['problem_key'],
                'etiology' => $etiology,
                'signs' => $signs,
                'evidence_used' => array_map(
                    fn (string $key): string => $this->formatEvidence($key, $candidate['evidence'][$key]),
                    array_values(array_unique($evidenceUsed)),
                ),
                'source' => $candidate['source'],
            ];
        }

        return $validated;
    }

    private function component(mixed $value, string $name): string
    {
        if (! is_string($value)) {
            throw new \RuntimeException("The AI returned an invalid PES {$name}.");
        }

        $normalized = trim((string) preg_replace('/\s+/', ' ', $value));
        if ($normalized === '' || mb_strlen($normalized) > 500) {
            throw new \RuntimeException("The AI returned an invalid PES {$name}.");
        }

        return $normalized;
    }

    private function formatEvidence(string $key, mixed $value): string
    {
        $label = match ($key) {
            'weight_loss_percentage' => 'Weight loss',
            'weight_change_period' => 'Weight-change period',
            'bmi' => 'BMI',
            'age_group' => 'Age group',
            'weight_kg' => 'Weight',
            'height_cm' => 'Height',
            'appetite' => 'Appetite',
            'present_diet' => 'Present diet',
            'energy_intake_status' => 'Energy intake status',
            'chewing_swallowing_difficulties' => 'Chewing/swallowing finding',
            'gi_symptoms' => 'GI finding',
            'food_intolerance' => 'Food intolerance',
            'nutrient_drug_interaction' => 'Food-medication interaction',
            'medication_count' => 'Medication count',
            default => str_replace('_', ' ', ucfirst($key)),
        };

        $display = match ($key) {
            'weight_loss_percentage' => rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%',
            'weight_kg' => rtrim(rtrim(number_format((float) $value, 2), '0'), '.').' kg',
            'height_cm' => rtrim(rtrim(number_format((float) $value, 2), '0'), '.').' cm',
            'weight_change_period' => $value['value'].' '.$value['unit'],
            'gi_symptoms' => implode('; ', array_values($value)),
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };

        return $label.': '.$display;
    }

    private function response(PesSuggestionState $state, bool $cached): array
    {
        $dismissed = array_fill_keys($state->dismissed_candidate_ids ?? [], true);
        $visible = array_values(array_filter(
            $state->validated_response,
            static fn (array $draft): bool => ! isset($dismissed[$draft['candidate_id']]),
        ));

        return [
            'data' => $visible,
            'meta' => [
                'cached' => $cached,
                'fingerprint' => $state->fingerprint,
                'catalog_version' => $state->catalog_version,
                'message' => $visible === [] ? self::EMPTY_MESSAGE : null,
            ],
        ];
    }

    private function fingerprint(array $evidence): string
    {
        $normalized = $this->sortRecursive($evidence);

        return hash('sha256', json_encode([
            'catalog_version' => $this->catalog->version(),
            'evidence' => $normalized,
        ], JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE));
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $sorted = array_map(fn (mixed $item): mixed => $this->sortRecursive($item), $value);
            sort($sorted);

            return $sorted;
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->sortRecursive($item), $value);
    }
}
