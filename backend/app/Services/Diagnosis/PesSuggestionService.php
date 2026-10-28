<?php

namespace App\Services\Diagnosis;

use App\Exceptions\UnusableAiDraftException;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\PesSuggestionState;
use App\Services\AIService;
use App\Support\AiReviewAllowance;
use App\Support\ClinicalAiContext;
use App\Support\DiagnosisBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PesSuggestionService
{
    public const EMPTY_MESSAGE = 'No supported evidence left.';

    public function __construct(
        private PesEvidenceBuilder $evidenceBuilder,
        private PesRuleCatalog $catalog,
        private PesEligibilityService $eligibility,
        private AIService $aiService,
    ) {}

    public function status(NcpRecord $ncpRecord): array
    {
        $evidence = $this->evidenceBuilder->build($ncpRecord);

        return $this->response($ncpRecord, $this->state($ncpRecord, $evidence), $evidence, true);
    }

    public function suggest(NcpRecord $ncpRecord, ?string $dismissedCandidateId = null, ?string $dismissedDraftId = null, bool $retryRejected = false): array
    {
        return Cache::lock('pes-patient-'.$ncpRecord->patient_id, 40)->block(1, function () use ($ncpRecord, $dismissedCandidateId, $dismissedDraftId, $retryRejected): array {
            $evidence = $this->evidenceBuilder->build($ncpRecord);
            if ($evidence['no_diagnosis_recorded'] ?? false) {
                throw new \InvalidArgumentException(Diagnosis::NO_DIAGNOSIS_WARNING);
            }
            $state = $this->state($ncpRecord, $evidence);
            if ($dismissedCandidateId !== null || $dismissedDraftId !== null) {
                $selected = collect($this->activeDrafts($ncpRecord, $evidence))->firstWhere($dismissedDraftId !== null ? 'draft_id' : 'candidate_id', $dismissedDraftId ?? $dismissedCandidateId);
                $source = $selected ? $this->history($ncpRecord)->first(fn ($item) => collect($item->validated_response)->contains('draft_id', $selected['draft_id'])) : null;
                if ($source === null) {
                    throw new \InvalidArgumentException('The selected PES draft is no longer available.');
                }
                $this->reopen($ncpRecord, $selected['candidate_id'], $selected['label'], $selected['draft_id'], $selected['domain']);

                return $this->response($ncpRecord, $state, $evidence, true);
            }
            $eligible = $this->remaining($ncpRecord, $evidence, $retryRejected);
            if ($eligible === []) {
                return $this->response($ncpRecord, $state, $evidence, true);
            }
            AiReviewAllowance::reserve('diagnosis');
            $rejected = false;
            $reviewEvidence = $evidence;
            $reviewEvidence['existing_problem_keys'] = array_values(array_unique([
                ...($evidence['existing_problem_keys'] ?? []),
                ...array_column($this->activeDrafts($ncpRecord, $evidence), 'label'),
            ]));
            [$payload, $candidateRefs, $evidenceRefs] = $this->providerPayload($eligible, $reviewEvidence);
            try {
                $result = $this->aiService->draftPes($payload);
            } catch (UnusableAiDraftException $error) {
                $rejected = true;
                Log::error('PES response withheld', ['reason' => $error->getMessage(), 'usage_log_id' => $error->usage['usage_log_id']]);
                $result = ['suggestions' => [], 'usage' => $error->usage];
            }
            $raw = $result['suggestions'];
            $validated = [];
            try {
                $validated = $this->validateProviderResponse($raw, $eligible, $reviewEvidence, $candidateRefs, $evidenceRefs);
            } catch (\RuntimeException $error) {
                $rejected = true;
                Log::error('PES response withheld', ['reason' => $error->getMessage(), 'usage_log_id' => $result['usage']['usage_log_id']]);
            } finally {
                Log::info('PES generation outcome', [
                    ...$result['usage'], 'returned_drafts' => count($raw),
                    'accepted_drafts' => count($validated), 'withheld_drafts' => count($raw) - count($validated),
                ]);
            }
            $previous = $state?->validated_response ?? [];
            $outcome = $rejected ? 'rejected' : (count($validated) < count($raw) ? 'partial' : ($validated === [] ? 'no_supported' : 'complete'));
            $newlyReviewed = array_column($eligible, 'candidate_id');
            $contexts = $state?->provider_metadata['review_contexts'] ?? [];
            foreach ($newlyReviewed as $id) {
                $extra = collect($validated)->where('candidate_id', $id)->pluck('evidence_refs')->flatten(1)->pluck('key')->unique()->values()->all();
                $card = collect($eligible)->firstWhere('candidate_id', $id);
                $candidateOutcome = $rejected ? 'rejected' : ($extra !== [] && $card['problem_key'] !== 'Other' ? 'complete' : 'not_returned');
                $contexts[$id] = ['hash' => $this->contextHash($id, $evidence, $extra), 'keys' => $extra, 'outcome' => $candidateOutcome];
            }
            $attributes = [
                'validated_response' => [...$previous, ...$validated],
                'dismissed_candidate_ids' => $state?->dismissed_candidate_ids ?? [],
                'provider_metadata' => ['provider' => 'anthropic', 'review_contexts' => $contexts,
                    'review_outcome' => $outcome, 'model' => config('services.anthropic.model')],
            ];
            if ($state === null) {
                $state = PesSuggestionState::create([
                    'ncp_record_id' => $ncpRecord->id,
                    'fingerprint' => $this->fingerprint($evidence),
                    'catalog_version' => $this->catalog->version(),
                    ...$attributes,
                ]);
            } else {
                $state->update($attributes);
            }

            return $this->response($ncpRecord, $state, $evidence, false);
        });
    }

    public function resolveDraft(NcpRecord $ncpRecord, string $draftId): array
    {
        $ncpRecord->unsetRelation('diagnoses');
        $evidence = $this->evidenceBuilder->build($ncpRecord);
        if ($evidence['no_diagnosis_recorded'] ?? false) {
            throw new \InvalidArgumentException(Diagnosis::NO_DIAGNOSIS_WARNING);
        }
        $draft = collect($this->activeDrafts($ncpRecord, $evidence))->firstWhere('draft_id', $draftId);
        $state = $draft ? $this->history($ncpRecord)->first(fn ($item) => collect($item->validated_response)->contains('draft_id', $draftId)) : null;
        if ($draft === null) {
            throw new \InvalidArgumentException('This draft is unavailable or its Assessment evidence changed. Refresh AI Review.');
        }

        return [
            'ai_draft_id' => $draftId, 'domain' => $draft['domain'], 'problem' => $draft['label'], 'label' => $draft['label'],
            'etiology' => $draft['etiology'], 'signs_symptoms' => $draft['signs'],
            'pes_statement' => Diagnosis::buildPes($draft['label'], $draft['etiology'], $draft['signs']),
            'extra_notes' => $draft['extra_notes'], 'builder_data' => $draft['builder_data'], 'ai_generated' => true,
            'ai_provenance' => [
                'draft_id' => $draftId, 'candidate_id' => $draft['candidate_id'],
                'fingerprint' => $state->fingerprint, 'catalog_version' => $state->catalog_version,
                'evidence_refs' => $draft['evidence_refs'], 'source' => $draft['source'],
                'ncp_record_uuid' => $ncpRecord->uuid,
                'assessment_record_id' => $ncpRecord->assessment?->id,
                'assessment_updated_at' => $ncpRecord->assessment?->updated_at?->toIso8601String(),
            ],
        ];
    }

    public function budget(NcpRecord $ncpRecord): array
    {
        return AiReviewAllowance::status('diagnosis');
    }

    public function reopen(NcpRecord $record, ?string $candidateId, string $label, ?string $draftId = null, ?string $domain = null): void
    {
        $cards = $this->catalog->cards();
        $problems = $this->eligibility->canonicalProblems([$label]);
        foreach ($this->history($record) as $state) {
            $metadata = $state->provider_metadata;
            if ($domain === 'NO') {
                unset($metadata['review_contexts'], $metadata['review_outcome']);
                $state->update(['validated_response' => [], 'provider_metadata' => $metadata]);

                continue;
            }
            $ids = collect($cards)->contains('id', $candidateId) ? [$candidateId] : [];
            foreach ($state->validated_response as $draft) {
                if (in_array($this->eligibility->canonicalProblem($draft['label']), $problems, true)) {
                    $ids[] = $draft['candidate_id'];
                }
            }
            foreach ($cards as $card) {
                if (($domain === null || $card['domain'] === $domain) && in_array($this->eligibility->canonicalProblem($card['problem_key']), $problems, true)) {
                    $ids[] = $card['id'];
                }
            }
            if ($ids === [] && $domain !== null) {
                $ids = array_column(array_filter($cards, fn ($card) => $card['domain'] === $domain && $card['problem_key'] === 'Other'), 'id');
            }
            foreach ($ids as $id) {
                unset($metadata['review_contexts'][$id]);
            }
            $state->update(['provider_metadata' => $metadata, 'validated_response' => array_values(array_filter(
                $state->validated_response,
                fn ($draft) => $draft['draft_id'] !== $draftId && ! in_array($this->eligibility->canonicalProblem($draft['label']), $problems, true),
            ))]);
        }
    }

    private function state(NcpRecord $ncpRecord, array $evidence): ?PesSuggestionState
    {
        return PesSuggestionState::query()->where('ncp_record_id', $ncpRecord->id)->where('fingerprint', $this->fingerprint($evidence))->where('catalog_version', $this->catalog->version())->first();
    }

    private function history(NcpRecord $record): Collection
    {
        return PesSuggestionState::query()->where('ncp_record_id', $record->id)->where('catalog_version', $this->catalog->version())->orderByDesc('id')->get();
    }

    private function contextHash(string $candidateId, array $evidence, array $extra = []): string
    {
        $card = collect($this->catalog->cards())->firstWhere('id', $candidateId);
        $keys = [...$extra, ...($card['required_evidence'] ?? []), ...($card['corroborating_evidence'] ?? []), 'age_group', 'age_70_plus', 'food_recall', 'intake_nutrients'];
        if (($card['domain'] ?? null) !== 'NB') {
            $keys = [...$keys, 'medical_history', 'clinical_context'];
        }
        if (($card['domain'] ?? null) === 'NC') {
            $keys = [...$keys, 'sex', 'edema_present', 'pregnancy_lactation_status', 'bmi', 'height_cm', 'weight_change_direction', 'weight_change_percentage', 'weight_change_period', 'weight_kg', 'usual_weight_kg'];
        }

        return $this->fingerprint(array_intersect_key($evidence, array_fill_keys($keys, true)));
    }

    private function remaining(NcpRecord $record, array $evidence, bool $retryRejected = false): array
    {
        $covered = [];
        foreach ($this->history($record) as $previous) {
            foreach ($previous->provider_metadata['review_contexts'] ?? [] as $id => $context) {
                if (! ($retryRejected && in_array($context['outcome'] ?? '', ['rejected', 'partial', 'not_returned', 'no_supported'], true)) && $context['hash'] === $this->contextHash($id, $evidence, $context['keys'] ?? [])) {
                    $covered[] = $id;
                }
            }
        }

        $evidence['existing_problem_keys'] = [
            ...($evidence['existing_problem_keys'] ?? []),
            ...array_column($this->activeDrafts($record, $evidence), 'label'),
        ];

        return $this->eligibility->eligible($evidence, array_values(array_unique($covered)));
    }

    private function activeDrafts(NcpRecord $record, array $evidence): array
    {
        if ($evidence['no_diagnosis_recorded'] ?? false) {
            return [];
        }
        $active = [];
        $seen = [];
        foreach ($this->history($record) as $previous) {
            foreach ($this->pending($previous, $evidence, $record) as $draft) {
                if (($draft['context_hash'] ?? null) !== $this->contextHash($draft['candidate_id'], $evidence, array_column($draft['evidence_refs'], 'key'))) {
                    continue;
                }
                $problem = $this->eligibility->canonicalProblem($draft['label']);
                if (! isset($seen[$problem])) {
                    $active[] = $draft;
                    $seen[$problem] = true;
                }
            }
        }

        return $active;
    }

    private function pending(PesSuggestionState $state, array $evidence, NcpRecord $record): array
    {
        $dismissed = $state->dismissed_candidate_ids ?? [];
        $accepted = $record->diagnoses->map(fn ($diagnosis) => $diagnosis->ai_provenance['draft_id'] ?? null)->filter()->all();
        $existing = $this->eligibility->canonicalProblems($evidence['existing_problem_keys'] ?? []);

        return array_values(array_filter($state->validated_response, fn ($draft): bool => ! in_array($draft['candidate_id'], $dismissed, true)
            && ! in_array($draft['candidate_id'], $evidence['saved_candidate_ids'] ?? [], true)
            && ! in_array($draft['draft_id'], $accepted, true)
            && ! in_array($this->eligibility->canonicalProblem($draft['label']), $existing, true)
        ));
    }

    private function response(NcpRecord $record, ?PesSuggestionState $state, array $evidence, bool $cached): array
    {
        $visible = $this->activeDrafts($record, $evidence);
        $budget = $this->budget($record);
        $remaining = $this->remaining($record, $evidence);
        $canRetry = count($this->remaining($record, $evidence, true)) > count($remaining);
        $outcome = $state?->provider_metadata['review_outcome'] ?? ($canRetry ? 'rejected' : null);
        $reason = match (true) {
            $evidence['no_diagnosis_recorded'] ?? false => Diagnosis::NO_DIAGNOSIS_WARNING,
            $budget['remaining'] === 0 => 'Ten requests per ten minutes reached. Try again when the allowance resets.',
            $canRetry && $remaining === [] => 'No additional supported draft was returned. Review the Assessment or try again.',
            $remaining === [] => self::EMPTY_MESSAGE.' Update the Assessment when new findings are available.',
            default => null,
        };

        return ['data' => $visible, 'meta' => [
            'cached' => $cached, 'fingerprint' => $this->fingerprint($evidence),
            'catalog_version' => $this->catalog->version(), 'budget' => $budget,
            'blocked_by_no_diagnosis' => (bool) ($evidence['no_diagnosis_recorded'] ?? false),
            'can_generate' => $reason === null, 'can_retry' => $canRetry && $budget['remaining'] > 0, 'review_outcome' => $outcome, 'disabled_reason' => $reason,
            'message' => ($evidence['no_diagnosis_recorded'] ?? false) ? Diagnosis::NO_DIAGNOSIS_WARNING : ($visible === [] ? ($canRetry ? $reason : ($remaining === [] ? self::EMPTY_MESSAGE : null)) : null),
        ]];
    }

    /** Only recorded concerns can support a cause; care grouping and medication names stay context. */
    private function causeEvidence(array $evidence): array
    {
        $evidence['clinical_context'] = $evidence['clinical_context']['referral_diagnosis'] ?? null;

        $causes = array_intersect_key($evidence, array_fill_keys([
            'appetite', 'dietary_intake', 'present_diet', 'knowledge_notes', 'lifestyle', 'social_history',
            'medical_history', 'clinical_context', 'physical_assessment', 'body_composition', 'food_intolerance',
            'nutrient_drug_interaction', 'dietary_restrictions', 'supplements', 'chewing_swallowing_difficulties', 'gi_symptoms',
        ], true));
        foreach ($causes as $key => $value) {
            if (! is_string($value)) {
                continue;
            }
            $causes[$key] = implode('; ', array_filter($this->eligibility->clauses($value), function ($clause): bool {
                $routine = preg_match('/\b(smok\w*|tobacco|cigarettes?|alcohol|caffeine|supplements?)\b/i', $clause);
                $recordedLink = preg_match('/\b(caus\w*|affect\w*|limit\w*|reduc\w*|impair\w*|interfer\w*|displac\w*|because|due to)\b/i', $clause)
                    && preg_match('/\b(intake|appetite|eating|meals?|swallow\w*|chew\w*|reflux|absorption|nutrition|tolerance)\b/i', $clause)
                    && ! preg_match('/\b(?:not|never)\b.{0,20}\b(?:cause|affect|reduce|impair|limit)\w*/i', $clause);

                return ! $routine || $recordedLink;
            }));
        }

        return array_filter($causes, fn ($value): bool => $this->eligibility->hasConcern($value));
    }

    /** Request-local aliases never enter stored drafts or clinical provenance. */
    private function providerPayload(array $eligible, array $evidence): array
    {
        unset($evidence['rnd_summary'], $evidence['saved_candidate_ids'], $evidence['no_diagnosis_recorded']);
        $existing = $evidence['existing_problem_keys'] ?? [];
        unset($evidence['existing_problem_keys']);
        // EvidenceBuilder already allowlists and bounds the clinical snapshot, including normal findings.
        $payload = ['evidence' => [], 'existing_problems' => $existing, 'candidates' => []];
        $guidance = ClinicalAiContext::guidance($evidence, 'diagnosis');
        if ($guidance !== '') {
            $payload['guidance'] = $guidance;
        }
        $candidateRefs = $evidenceRefs = [];
        foreach ($evidence as $key => $value) {
            $ref = 'e'.(count($evidenceRefs) + 1);
            $evidenceRefs[$ref] = $key;
            $payload['evidence'][$ref] = [$key, $value];
        }
        $causes = $this->causeEvidence($evidence);
        foreach ($eligible as $candidate) {
            $ref = 'c'.(count($candidateRefs) + 1);
            $candidateRefs[$ref] = $candidate['candidate_id'];
            $card = collect($this->catalog->cards())->firstWhere('id', $candidate['candidate_id']);
            $anchors = array_keys(array_intersect($evidenceRefs, $card['required_evidence'] ?? []));
            $payload['candidates'][$ref] = [$candidate['domain'], DiagnosisBuilder::problemLabel($candidate['domain'], $candidate['problem_key']), $anchors];
            if (! isset($payload['choices'][$candidate['domain']])) {
                $options = DiagnosisBuilder::options()[$candidate['domain']];
                $suppliedProblems = array_map(fn ($item) => DiagnosisBuilder::problemLabel($item['domain'], $item['problem_key']), array_filter($eligible, fn ($item) => $item['domain'] === $candidate['domain']));
                $payload['choices'][$candidate['domain']] = [
                    'p' => $candidate['domain'] === 'NI' ? ['Inadequate/Excessive [nutrient] intake', 'Inadequate/Excessive enteral/parenteral nutrition infusion', 'Predicted suboptimal/excessive energy/nutrient intake', 'Increased/Decreased nutrient needs', 'Less than optimal nutrition composition/modality or intake of types of fats/proteins/carbohydrate', 'Increased energy expenditure', 'Limited food acceptance', 'Suboptimal/Excessive bioactive substance intake', 'Excessive alcohol intake', 'Malnutrition', 'Inconsistent carbohydrate intake', 'Imbalance of nutrients'] : array_values(array_diff(array_map(DiagnosisBuilder::cleanProblemTerm(...), $options['problems']), $suppliedProblems)),
                    'e' => array_values(array_filter($options['etiologies'], fn ($choice): bool => $this->groundedWording($choice, array_keys($causes), $causes) && ! $this->hasNewNumbers($choice, $causes))),
                    's' => array_values(array_filter($options['signs'], fn ($choice): bool => $this->groundedWording($choice, array_keys($evidence), $evidence) && ! $this->hasNewNumbers($choice, $evidence))),
                ];
            }
        }
        $guidanceLines = isset($payload['guidance']) ? explode("\n", $payload['guidance']) : [];
        while ($guidanceLines !== [] && strlen((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > AIService::PES_INPUT_BYTE_LIMIT) {
            array_pop($guidanceLines);
            $payload['guidance'] = implode("\n", $guidanceLines);
        }
        if (($payload['guidance'] ?? '') === '') {
            unset($payload['guidance']);
        }

        return [$payload, $candidateRefs, $evidenceRefs];
    }

    private function validateProviderResponse(array $raw, array $eligible, array $evidence, array $candidateRefs, array $evidenceRefs): array
    {
        if (! array_is_list($raw)) {
            throw new \RuntimeException('The AI returned an unexpected diagnosis list.');
        }
        $raw = array_slice($raw, 0, AIService::PES_DRAFT_LIMIT);
        $byId = collect($eligible)->keyBy('candidate_id');
        $seen = [];
        $problems = $this->eligibility->canonicalProblems($evidence['existing_problem_keys'] ?? []);
        $validated = [];
        $draftError = null;
        foreach ($raw as $draft) {
            try {
                if (! is_array($draft)) {
                    throw new \RuntimeException('The AI returned an unexpected diagnosis shape.');
                }
                $id = array_key_exists('id', $draft)
                    ? (is_string($draft['id']) ? ($candidateRefs[$draft['id']] ?? null) : null)
                    : ($draft['candidate_id'] ?? null);
                if (! is_string($id) || ! $byId->has($id) || (isset($seen[$id]) && $byId[$id]['problem_key'] !== 'Other')) {
                    throw new \RuntimeException('The AI returned an unsupported or duplicate PES draft.');
                }
                $candidate = $byId[$id];
                if (array_key_exists('id', $draft)) {
                    foreach (['e', 's'] as $role) {
                        $keys = $draft[$role.'k'] ?? null;
                        if (! is_string($draft[$role] ?? null) || ! is_array($keys)
                            || count(array_filter($keys, 'is_string')) !== count($keys)
                            || array_diff($keys, array_keys($evidenceRefs)) !== []) {
                            throw new \RuntimeException('The AI did not ground both the cause and signs in supplied evidence.');
                        }
                        $draft[$role.'k'] = array_map(fn ($key) => $evidenceRefs[$key], $keys);
                    }
                    $draft = [
                        'candidate_id' => $id, 'domain' => $candidate['domain'], 'source_id' => $candidate['source']['id'],
                        'problem_key' => $candidate['problem_key'] === 'Other' ? ($draft['p'] ?? null) : $candidate['problem_key'],
                        'etiology' => $draft['e'], 'signs' => $draft['s'],
                        'etiology_evidence' => $draft['ek'], 'signs_evidence' => $draft['sk'],
                        'evidence_used' => array_values(array_unique([...$draft['ek'], ...$draft['sk']])),
                        'extra_notes' => $draft['n'] ?? '', 'etiology_notes' => $draft['en'] ?? '', 'sign_notes' => $draft['sn'] ?? '',
                    ];
                }
                if (($draft['domain'] ?? null) !== $candidate['domain'] || ($draft['source_id'] ?? null) !== $candidate['source']['id']) {
                    throw new \RuntimeException('The AI returned an unsupported PES domain or source.');
                }
                $label = $candidate['problem_key'] === 'Other' ? $this->component($draft['problem_key'] ?? null, 255) : $candidate['problem_key'];
                if ($candidate['problem_key'] !== 'Other' && ($draft['problem_key'] ?? null) !== $label) {
                    throw new \RuntimeException('The AI changed a supplied nutrition problem.');
                }
                $canonical = $this->eligibility->canonicalProblem($label);
                if ($canonical === $this->eligibility->canonicalProblem(Diagnosis::NO_DIAGNOSIS)) {
                    throw new \RuntimeException('A no-diagnosis decision must be recorded explicitly by the nutritionist.');
                }
                foreach (DiagnosisBuilder::options() as $optionDomain => $optionGroups) {
                    if ($optionDomain !== $candidate['domain'] && in_array($label, $optionGroups['problems'], true)) {
                        throw new \RuntimeException('The AI assigned a known problem to the wrong diagnostic domain.');
                    }
                }
                $pediatricWeight = ($evidence['age_group'] ?? '') === 'pediatric'
                    && in_array($evidence['bmi_classification'] ?? '', ['Overweight', 'Obesity'], true);
                if ($canonical === 'overweight obesity' && ! $pediatricWeight && ! collect($eligible)->contains('candidate_id', 'overweight_obesity_v1')) {
                    throw new \RuntimeException('Adult BMI classification is not supported for this Assessment context.');
                }
                if ($canonical === 'overweight obesity') {
                    if (str_contains(mb_strtolower($label), 'obese, pediatric') && (! $pediatricWeight || $evidence['bmi_classification'] !== 'Obesity')) {
                        throw new \RuntimeException('The pediatric term requires the documented pediatric classification.');
                    }
                    if (preg_match('/\bclass\s+(I{1,3})\b/i', $label, $class) && ($pediatricWeight || ! preg_match('/\bclass\s+'.preg_quote($class[1], '/').'$/i', $evidence['bmi_classification'] ?? ''))) {
                        throw new \RuntimeException('The obesity class was not established by the supplied classification.');
                    }
                }
                if ($candidate['domain'] === 'NI' && ! DiagnosisBuilder::isIntakeProblem($label)) {
                    throw new \RuntimeException('Use the supported intake direction and nutrient format.');
                }
                if (preg_match('/\b(diabetes(?: mellitus)?|hypertension|cancer|chronic kidney disease|CKD|heart failure|renal failure|cirrhosis|hepatitis|pneumonia|COPD|asthma|stroke|sepsis|anemia|anaemia)\b/i', $label)) {
                    throw new \RuntimeException('Medical diagnoses cannot replace nutrition problems.');
                }
                if (in_array($canonical, $problems, true) || in_array($id, $evidence['saved_candidate_ids'] ?? [], true)) {
                    continue;
                }
                $keys = $draft['evidence_used'] ?? null;
                if (! is_array($keys) || $keys === [] || count($keys) > 8) {
                    throw new \RuntimeException('The AI returned a PES draft without bounded evidence.');
                }
                $refs = [];
                foreach ($keys as $key) {
                    if (! is_string($key) || ! in_array($key, $evidenceRefs, true) || ! array_key_exists($key, $evidence)) {
                        throw new \RuntimeException('The AI returned evidence that was not supplied.');
                    }
                    $value = $evidence[$key];
                    $serialized = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
                    $refs[] = ['key' => $key, 'value_hash' => hash('sha256', json_encode($value)), 'quote' => mb_substr($serialized, 0, 600)];
                }
                $roles = [];
                foreach (['etiology_evidence', 'signs_evidence'] as $role) {
                    $roleKeys = $draft[$role] ?? null;
                    if (! is_array($roleKeys) || $roleKeys === [] || count($roleKeys) > 8 || count(array_filter($roleKeys, 'is_string')) !== count($roleKeys) || array_diff($roleKeys, $keys) !== []) {
                        throw new \RuntimeException('The AI did not ground both the cause and signs in supplied evidence.');
                    }
                    $roles[$role] = $roleKeys;
                }
                $causeEvidence = $this->causeEvidence($evidence);
                $causes = array_intersect($roles['etiology_evidence'], array_keys($causeEvidence));
                if ($causes === []) {
                    throw new \RuntimeException('The AI inferred a cause without a documented concern.');
                }
                $signConcerns = array_filter($roles['signs_evidence'], function ($key) use ($evidence): bool {
                    return match ($key) {
                        'bmi' => in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true)
                            && (($evidence['bmi'] ?? 0) >= 23 || (($evidence['bmi'] ?? 0) > 0 && $evidence['bmi'] < (($evidence['age_70_plus'] ?? false) ? 20 : 18.5))),
                        'weight_loss_percentage', 'weight_change_percentage' => ($evidence['weight_change_direction'] ?? 'unknown') === 'loss' && ($evidence[$key] ?? 0) > 0,
                        'laboratory_flags' => ! empty($evidence[$key]),
                        'physical_activity_level' => in_array($evidence[$key], ['sedentary', 'inactive'], true),
                        'bmi_classification' => ($evidence['age_group'] ?? '') === 'pediatric' && in_array($evidence[$key], ['Overweight', 'Obesity'], true),
                        'age_group', 'age_70_plus', 'sex', 'weight_kg', 'usual_weight_kg', 'height_cm', 'weight_change_direction', 'weight_change_period', 'pregnancy_lactation_status', 'functional_assessment', 'medication_count', 'laboratory_values', 'ibw_percentage', 'muac_mm', 'waist_cm', 'hip_cm', 'food_recall', 'intake_nutrients', 'clinical_context', 'nutrition_support' => false,
                        default => $this->eligibility->hasConcern($evidence[$key]),
                    };
                });
                if ($signConcerns === []) {
                    throw new \RuntimeException('The AI used only normal or contextual values as proof of a problem.');
                }
                $signEvidence = array_intersect_key($evidence, array_fill_keys([...$signConcerns,
                    'age_group', 'age_70_plus', 'sex', 'edema_present', 'pregnancy_lactation_status', 'weight_change_direction', 'weight_change_period', 'medication_count'], true));
                $requiredCandidates = [$id];
                if ($candidate['problem_key'] === 'Other') {
                    $known = collect($this->catalog->cards())->filter(fn ($card) => $card['domain'] === $candidate['domain']
                        && $card['problem_key'] !== 'Other'
                        && in_array($canonical, $this->eligibility->canonicalProblems([$card['problem_key']]), true))->pluck('id')->all();
                    $requiredCandidates = $known ?: $requiredCandidates;
                }
                if (! ($candidate['problem_key'] === 'Other' && $pediatricWeight && $canonical === 'overweight obesity' && in_array('bmi_classification', $signConcerns, true))
                    && array_intersect($requiredCandidates, array_column($this->eligibility->eligible($signEvidence), 'candidate_id')) === []) {
                    throw new \RuntimeException('The cited signs do not establish the selected nutrition problem.');
                }
                $etiology = $this->component($draft['etiology'] ?? null, 75);
                $signs = $this->component($draft['signs'] ?? null, 75);
                if (! $this->eligibility->hasConcern($etiology) || ! $this->eligibility->hasConcern($signs)) {
                    throw new \RuntimeException('Normal or denied findings cannot establish a cause or signs.');
                }
                $etiology = $this->groundedComponent($etiology, array_values($causes), $causeEvidence);
                $signs = $this->groundedComponent($signs, $roles['signs_evidence'], $evidence);
                if ($candidate['domain'] === 'NI' && preg_match('/^(Inadequate|Excessive) .+ intake(?:\b|:)/i', $label) && (! $this->eligibility->supportsIntakeProblem($label, $signs)
                    || ! $this->eligibility->supportsIntakeProblem($label, $this->eligibility->intakeFinding($evidence)))) {
                    throw new \RuntimeException('The signs do not support the selected intake nutrient and direction.');
                }
                if ($canonical === 'underweight' && (($evidence['bmi_classification'] ?? null) !== 'Underweight' || ! in_array('bmi', $roles['signs_evidence'], true))) {
                    throw new \RuntimeException('Underweight requires the supported adult BMI finding.');
                }
                if ($candidate['problem_key'] === 'Other' && $candidate['domain'] !== 'NI'
                    && $canonical !== 'underweight' && ! preg_match('/\b(malnutrition|undernutrition)\b/', $canonical)) {
                    $nutritionTerms = $candidate['domain'] === 'NB'
                        ? '/\b(food|nutrition|diet|meal|eating|activity|exercise|education|adherence|monitoring)\b/i'
                        : '/\b(nutrition|nutritional|intake|absorption|gastrointestinal|swallowing|chewing|weight|growth|muscle)\b/i';
                    $findings = array_diff($keys, ['clinical_context', 'medical_history']);
                    if (! in_array(DiagnosisBuilder::cleanProblemTerm($label), array_map(DiagnosisBuilder::cleanProblemTerm(...), DiagnosisBuilder::options()[$candidate['domain']]['problems']), true)
                        && (! preg_match($nutritionTerms, $label) || ! $this->groundedWording($label, $findings, $evidence))) {
                        throw new \RuntimeException('The custom problem lacks corresponding nutrition evidence.');
                    }
                }
                $notes = [];
                $noteEvidence = [...$evidence, 'rationale_context' => $label.' Evidence supports problem; documented cause barrier; specific reported finding gap; qualifies signs symptoms.'];
                $noteKey = static fn (string $text): string => trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text)));
                $components = array_map($noteKey, [$label, $etiology, $signs]);
                $usedNotes = [];
                $citedEvidence = array_intersect_key($evidence, array_fill_keys($keys, true));
                foreach (['extra_notes', 'etiology_notes', 'sign_notes'] as $field) {
                    $note = $this->component(is_string($draft[$field] ?? null) ? $draft[$field] : '', 100, true);
                    $noteValue = $noteKey((string) preg_replace('/^(?:supported by|documented cause|recorded sign)\s*:\s*/i', '', $note));
                    $notes[$field] = ! $this->hasNewNumbers($note, $citedEvidence) && ! in_array($noteValue, [...$components, ...$usedNotes], true)
                        && preg_match('/\b(because|support\w*|indicat\w*|explain\w*|reflect\w*|qualif\w*|evidence|barrier|cause|demonstrat\w*|links?)\b/i', $note)
                        && $this->groundedWording($note, [...$keys, 'rationale_context'], $noteEvidence) ? $note : '';
                    if ($notes[$field] !== '') {
                        $usedNotes[] = $noteValue;
                    }
                }
                foreach ([$label, $etiology, $signs] as $wording) {
                    if ($this->hasNewNumbers($wording, $citedEvidence)) {
                        throw new \RuntimeException('The AI added a measurement or threshold not present in its evidence.');
                    }
                }
                if (str_contains($canonical, 'weight loss') && ($evidence['weight_change_direction'] ?? 'unknown') !== 'loss') {
                    throw new \RuntimeException('Weight gain or unknown direction cannot support unintended weight loss.');
                }
                if (str_contains($canonical, 'weight loss') && ! $this->affirmativeFinding(array_intersect_key($evidence, array_fill_keys($keys, true)), '/\b(unintentional|unintended|non.?volitional|without trying|not intentional)\b/i')) {
                    throw new \RuntimeException('Unintended weight loss requires documentation of intentionality.');
                }
                if (preg_match('/\b(malnutrition|undernutrition)\b/', $canonical) && ! $this->supportsMalnutrition($evidence, $roles)) {
                    throw new \RuntimeException('Malnutrition requires independently supported clinical criteria.');
                }
                $seen[$id] = true;
                $domain = preg_match('/\b(malnutrition|undernutrition)\b/', $canonical) ? 'NI' : $candidate['domain'];
                $label = DiagnosisBuilder::problemLabel($domain, $label, $signs);
                $canonical = $this->eligibility->canonicalProblem($label);
                if (in_array($canonical, $problems, true)) {
                    continue;
                }
                $problems[] = $canonical;
                $builder = DiagnosisBuilder::fromDraft($domain, $label, $etiology, $signs, $notes);
                $validated[] = [
                    'draft_id' => (string) Str::uuid(), 'candidate_id' => $id, 'domain' => $domain,
                    'label' => $label, 'etiology' => $etiology, 'signs' => $signs,
                    'builder_data' => $builder, ...$notes, 'evidence_refs' => $refs,
                    'context_hash' => $this->contextHash($id, $evidence, array_column($refs, 'key')),
                    'evidence_used' => array_map(fn ($key) => $this->formatEvidence($key, $evidence[$key]), array_values(array_unique($keys))),
                    'source' => $candidate['source'],
                ];
            } catch (\RuntimeException $error) {
                $draftError = $error;
                Log::warning('PES draft withheld', ['reason' => $error->getMessage()]);
            }
        }
        if ($validated === [] && $draftError !== null) {
            throw $draftError;
        }

        return $validated;
    }

    private function affirmativeFinding(array $values, string $pattern): bool
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }
            foreach (preg_split('/[.!?;\n]+/', $value) as $sentence) {
                if (preg_match($pattern, $sentence) && ! preg_match('/\b(denies|denied|no|without|resolved|historical|history of|suspected|possible)\b/i', preg_replace('/without trying/i', '', $sentence))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function supportsMalnutrition(array $evidence, array $roles): bool
    {
        if (! in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true)
            || ($evidence['pregnancy_lactation_status'] ?? 'none') !== 'none' || ($evidence['edema_present'] ?? false)) {
            return false;
        }
        $signs = array_intersect_key($evidence, array_fill_keys($roles['signs_evidence'], true));
        $causes = array_intersect_key($evidence, array_fill_keys($roles['etiology_evidence'], true));
        $lowBmi = isset($signs['bmi']) && $signs['bmi'] > 0 && $signs['bmi'] < (($evidence['age_70_plus'] ?? false) ? 20 : 18.5);
        $period = $evidence['weight_change_period'] ?? [];
        $months = ($period['value'] ?? 0) / (($period['unit'] ?? '') === 'weeks' ? 4.345 : 1);
        $loss = ($evidence['weight_change_direction'] ?? '') === 'loss'
            && (isset($signs['weight_change_percentage']) || isset($signs['weight_loss_percentage']));
        $loss = $loss && $months > 0 && ($evidence['weight_loss_percentage'] ?? 0) > ($months <= 6 ? 5 : 10)
            && $this->affirmativeFinding([...$signs, ...$causes], '/\b(unintentional|unintended|non.?volitional|without trying|not intentional)\b/i');
        $muscle = $this->affirmativeFinding(array_intersect_key($signs, array_flip(['physical_assessment', 'body_composition'])), '/\b(reduced|low|depleted|loss of) muscle mass\b/i');
        $intake = $this->affirmativeFinding(array_intersect_key($causes, array_flip(['dietary_intake', 'present_diet'])), '/\b(reduced|decreased|poor|inadequate) (?:food |oral |energy )?intake\b.*\b(?:(?:[2-9]|[1-9]\d+|two|three|four) weeks?|(?:1[4-9]|[2-9]\d+) days?|(?:[1-9]\d*|one|two|three) months?)\b/i');
        $disease = $this->affirmativeFinding(array_intersect_key($causes, array_flip(['medical_history', 'physical_assessment'])), '/\b(?:(?:acute|chronic|active) inflammation|chronic malabsorption)\b/i');

        return ($lowBmi || $loss || $muscle) && ($intake || $disease);
    }

    private function component(mixed $value, int $limit, bool $optional = false): string
    {
        if (! is_string($value)) {
            throw new \RuntimeException('The AI returned invalid diagnosis text.');
        }
        $text = trim((string) preg_replace('/\s+/', ' ', $value));
        if ($optional && mb_strlen($text) > $limit) {
            return '';
        }
        if ((! $optional && $text === '') || mb_strlen($text) > $limit) {
            throw new \RuntimeException('The AI returned diagnosis text exceeding its field limit.');
        }

        return $text;
    }

    private function hasNewNumbers(string $wording, array $evidence): bool
    {
        preg_match_all('/\d+(?:\.\d+)?/', $wording, $numbers);
        $source = json_encode($evidence);
        if (preg_match('/\b(intake|needs|requirements)\b/i', $wording)) {
            preg_match_all('/([<>]?)\s*(\d+(?:\.\d+)?)\s*%/', $wording, $percentages, PREG_SET_ORDER);
            preg_match_all('/([<>]?)\s*(\d+(?:\.\d+)?)\s*%/', $source, $sourcePercentages, PREG_SET_ORDER);
            foreach ($percentages as $percentage) {
                if (! collect($sourcePercentages)->contains(fn ($recorded) => $recorded[1] === $percentage[1] && $recorded[2] === $percentage[2])) {
                    return true;
                }
            }
        }

        return array_filter($numbers[0], fn ($number) => ! preg_match('/(?<!\d)'.preg_quote($number, '/').'(?!\d)/', $source)) !== [];
    }

    private function groundedWording(string $wording, array $keys, array $evidence): bool
    {
        $normalize = static function (string $text): array {
            $text = mb_strtolower($text);
            $text = preg_replace('/\b(poor|low|reduced|decreased)\b/', 'reduced', $text);
            $text = preg_replace('/\b(limited|inadequate|insufficient|lack)\b/', 'limited', $text);
            $text = preg_replace('/\b(unintentional|unintended)\b/', 'unintended', $text);
            preg_match_all('/[\p{L}]+/u', $text, $words);
            $stop = ['a', 'an', 'the', 'of', 'and', 'or', 'to', 'for', 'from', 'with', 'by', 'in', 'over', 'as', 'at', 'related', 'due', 'documented', 'reported', 'reports', 'noted', 'estimated'];

            return array_values(array_unique(array_diff(array_map(static fn ($word) => $word === 'difficulties' ? 'difficulty' : rtrim($word, 's'), $words[0]), $stop)));
        };
        $source = [];
        foreach ($keys as $key) {
            $source[$key] = $this->concernEvidence($evidence[$key]);
        }
        $words = $normalize($wording);
        $supported = $normalize(json_encode($source, JSON_UNESCAPED_UNICODE));

        return $words !== [] && count(array_intersect($words, $supported)) / count($words) >= 2 / 3;
    }

    /** Preserve source wording when lexical matching cannot verify a paraphrase. */
    private function groundedComponent(string $wording, array $keys, array $evidence): string
    {
        $cited = array_intersect_key($evidence, array_fill_keys($keys, true));
        if ($this->hasNewNumbers($wording, $cited)) {
            throw new \RuntimeException('The AI added a measurement or threshold not present in its evidence.');
        }
        if ($this->groundedWording($wording, $keys, $evidence)) {
            return $wording;
        }
        $findings = [];
        foreach ($cited as $key => $value) {
            if (! is_string($value) || $key === 'clinical_context') {
                continue;
            }
            $finding = $this->concernEvidence($value);
            if ($finding !== '') {
                $findings[] = str_word_count($finding) === 1 ? $this->formatEvidence($key, $finding) : $finding;
            }
        }
        $source = implode('; ', array_unique($findings));
        if ($source === '' || mb_strlen($source) > 75) {
            throw new \RuntimeException('The cited findings need concise clinical wording before a draft can be generated.');
        }

        return $source;
    }

    private function concernEvidence(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->concernEvidence($item), $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        return implode('; ', array_filter(
            $this->eligibility->clauses($value),
            fn ($sentence) => $this->eligibility->hasConcern($sentence),
        ));
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
            'food_recall' => 'Food recall',
            'intake_nutrients' => 'Recorded intake nutrients',
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

    private function fingerprint(array $evidence): string
    {
        unset($evidence['existing_problem_keys'], $evidence['saved_candidate_ids']);
        $normalize = function ($value) use (&$normalize) {
            if (! is_array($value)) {
                return is_string($value) ? mb_strtolower(trim($value)) : $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normalize, $value);
        };

        return hash('sha256', json_encode(['catalog' => $this->catalog->version(), 'evidence' => $normalize($evidence)], JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE));
    }
}
