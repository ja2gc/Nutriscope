<?php

namespace App\Services\Diagnosis;

use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\PesSuggestionState;
use App\Services\AIService;
use App\Support\DiagnosisBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PesSuggestionService
{
    public const EMPTY_MESSAGE = 'No new supported nutrition problems were found.';

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

    public function suggest(NcpRecord $ncpRecord, ?string $dismissedCandidateId = null): array
    {
        return Cache::lock('pes-patient-'.$ncpRecord->patient_id, 40)->block(1, function () use ($ncpRecord, $dismissedCandidateId): array {
            $evidence = $this->evidenceBuilder->build($ncpRecord);
            $state = $this->state($ncpRecord, $evidence);
            if ($dismissedCandidateId !== null) {
                $selected = collect($this->activeDrafts($ncpRecord, $evidence))->firstWhere('candidate_id', $dismissedCandidateId);
                $source = $selected ? $this->history($ncpRecord)->first(fn ($item) => collect($item->validated_response)->contains('draft_id', $selected['draft_id'])) : null;
                if ($source === null) {
                    throw new \InvalidArgumentException('The selected PES draft is no longer available.');
                }
                $source->update(['dismissed_candidate_ids' => array_values(array_unique([...($source->dismissed_candidate_ids ?? []), $dismissedCandidateId]))]);

                return $this->response($ncpRecord, $state, $evidence, true);
            }
            $eligible = $this->remaining($ncpRecord, $evidence);
            if ($eligible === []) {
                return $this->response($ncpRecord, $state, $evidence, true);
            }
            $date = now('Asia/Manila')->toDateString();
            $this->reserve($ncpRecord, $date);
            try {
                $reviewEvidence = $evidence;
                $reviewEvidence['existing_problem_keys'] = array_values(array_unique([
                    ...($evidence['existing_problem_keys'] ?? []),
                    ...array_column($this->activeDrafts($ncpRecord, $evidence), 'label'),
                ]));
                $providerEvidence = $evidence;
                unset($providerEvidence['existing_problem_keys']);
                $domains = array_unique(array_column($eligible, 'domain'));
                $options = array_intersect_key(DiagnosisBuilder::options(), array_fill_keys($domains, true));
                foreach ($options as &$domainOptions) {
                    unset($domainOptions['problems']);
                }
                unset($domainOptions);
                $raw = $this->aiService->draftPes([
                    'catalog_version' => $this->catalog->version(),
                    'evidence' => $providerEvidence,
                    'existing_problems' => $reviewEvidence['existing_problem_keys'],
                    'candidates' => array_map(static fn (array $item): array => [
                        'id' => $item['candidate_id'], 'd' => $item['domain'], 'p' => $item['problem_key'], 's' => $item['source']['id'],
                    ], $eligible),
                    'options' => $options,
                ]);
                $validated = $this->validateProviderResponse($raw, $eligible, $reviewEvidence);
                $previous = $state?->validated_response ?? [];
                $reviewed = $state?->provider_metadata['reviewed_candidate_ids'] ?? [];
                $newlyReviewed = count($validated) < 3 ? array_column($eligible, 'candidate_id') : array_column($validated, 'candidate_id');
                $reviewed = array_values(array_unique([
                    ...$reviewed,
                    ...$newlyReviewed,
                ]));
                $contexts = $state?->provider_metadata['review_contexts'] ?? [];
                foreach ($newlyReviewed as $id) {
                    $issued = collect($validated)->firstWhere('candidate_id', $id);
                    $extra = array_column($issued['evidence_refs'] ?? [], 'key');
                    $contexts[$id] = ['hash' => $this->contextHash($id, $evidence, $extra), 'keys' => $extra];
                }
                $attributes = [
                    'validated_response' => [...$previous, ...$validated],
                    'dismissed_candidate_ids' => $state?->dismissed_candidate_ids ?? [],
                    'provider_metadata' => ['provider' => 'anthropic', 'reviewed_candidate_ids' => $reviewed, 'review_contexts' => $contexts, 'model' => config('services.anthropic.model')],
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
            } catch (\Throwable $error) {
                DB::table('patient_pes_daily_generations')->where('patient_id', $ncpRecord->patient_id)->where('generation_date', $date)->where('generations', '>', 0)->decrement('generations');
                throw $error;
            }

            return $this->response($ncpRecord, $state, $evidence, false);
        });
    }

    public function resolveDraft(NcpRecord $ncpRecord, string $draftId): array
    {
        $ncpRecord->unsetRelation('diagnoses');
        $evidence = $this->evidenceBuilder->build($ncpRecord);
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
        $now = now('Asia/Manila');
        $used = (int) DB::table('patient_pes_daily_generations')->where('patient_id', $ncpRecord->patient_id)->where('generation_date', $now->toDateString())->value('generations');

        return ['limit' => 3, 'used' => $used, 'remaining' => max(0, 3 - $used), 'resets_at' => $now->copy()->addDay()->startOfDay()->toIso8601String()];
    }

    private function reserve(NcpRecord $ncpRecord, string $date): void
    {
        DB::transaction(function () use ($ncpRecord, $date): void {
            DB::table('patients')->where('id', $ncpRecord->patient_id)->lockForUpdate()->first(['id']);
            $used = (int) DB::table('patient_pes_daily_generations')->where('patient_id', $ncpRecord->patient_id)->where('generation_date', $date)->value('generations');
            if ($used >= 3) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Daily limit reached: three AI generations for this patient. Try again after midnight Philippine time.',
                    'meta' => ['budget' => $this->budget($ncpRecord)],
                ], 429));
            }
            DB::table('patient_pes_daily_generations')->updateOrInsert(
                ['patient_id' => $ncpRecord->patient_id, 'generation_date' => $date], ['generations' => $used + 1],
            );
        });
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
        $keys = [...($extra !== [] ? $extra : [...($card['required_evidence'] ?? []), ...($card['corroborating_evidence'] ?? [])]), 'age_group', 'age_70_plus', 'medical_history'];
        if (($card['domain'] ?? null) === 'NC') {
            $keys = [...$keys, 'sex', 'edema_present', 'pregnancy_lactation_status', 'bmi', 'height_cm', 'weight_change_direction', 'weight_change_percentage', 'weight_change_period', 'weight_kg', 'usual_weight_kg'];
        }

        return $this->fingerprint(array_intersect_key($evidence, array_fill_keys($keys, true)));
    }

    private function remaining(NcpRecord $record, array $evidence): array
    {
        $covered = [];
        foreach ($this->history($record) as $previous) {
            foreach ($previous->provider_metadata['review_contexts'] ?? [] as $id => $context) {
                if ($context['hash'] === $this->contextHash($id, $evidence, $context['keys'] ?? [])) {
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
            && ! in_array($draft['draft_id'], $accepted, true)
            && ! in_array($this->eligibility->canonicalProblem($draft['label']), $existing, true)
        ));
    }

    private function response(NcpRecord $record, ?PesSuggestionState $state, array $evidence, bool $cached): array
    {
        $visible = $this->activeDrafts($record, $evidence);
        $budget = $this->budget($record);
        $remaining = $this->remaining($record, $evidence);
        $reason = match (true) {
            $budget['remaining'] === 0 => 'Daily limit reached (3/3). Available again after midnight Philippine time.',
            $remaining === [] => self::EMPTY_MESSAGE.' Update the Assessment when new findings are available.',
            default => null,
        };

        return ['data' => $visible, 'meta' => [
            'cached' => $cached, 'fingerprint' => $this->fingerprint($evidence),
            'catalog_version' => $this->catalog->version(), 'budget' => $budget,
            'can_generate' => $reason === null, 'disabled_reason' => $reason,
            'message' => $visible === [] ? ($remaining === [] ? self::EMPTY_MESSAGE : null) : null,
        ]];
    }

    private function validateProviderResponse(array $raw, array $eligible, array $evidence): array
    {
        if (! array_is_list($raw) || count($raw) > 3) {
            throw new \RuntimeException('The AI returned too many PES drafts.');
        }
        $byId = collect($eligible)->keyBy('candidate_id');
        $seen = [];
        $problems = $this->eligibility->canonicalProblems($evidence['existing_problem_keys'] ?? []);
        $validated = [];
        foreach ($raw as $draft) {
            if (! is_array($draft)) {
                throw new \RuntimeException('The AI returned an unexpected diagnosis shape.');
            }
            $id = $draft['candidate_id'] ?? null;
            if (! is_string($id) || isset($seen[$id]) || ! $byId->has($id)) {
                throw new \RuntimeException('The AI returned an unsupported or duplicate PES draft.');
            }
            $candidate = $byId[$id];
            if (($draft['domain'] ?? null) !== $candidate['domain'] || ($draft['source_id'] ?? null) !== $candidate['source']['id']) {
                throw new \RuntimeException('The AI returned an unsupported PES domain or source.');
            }
            $label = $candidate['problem_key'] === 'Other' ? $this->component($draft['problem_key'] ?? null, 75) : $candidate['problem_key'];
            if ($candidate['problem_key'] !== 'Other' && ($draft['problem_key'] ?? null) !== $label) {
                throw new \RuntimeException('The AI changed a supplied nutrition problem.');
            }
            $canonical = $this->eligibility->canonicalProblem($label);
            foreach (DiagnosisBuilder::options() as $optionDomain => $optionGroups) {
                if ($optionDomain !== $candidate['domain'] && in_array($label, $optionGroups['problems'], true)) {
                    throw new \RuntimeException('The AI assigned a known problem to the wrong diagnostic domain.');
                }
            }
            if ($canonical === 'overweight obesity' && ! collect($eligible)->contains('candidate_id', 'overweight_obesity_v1')) {
                throw new \RuntimeException('Adult BMI classification is not supported for this Assessment context.');
            }
            if ($candidate['domain'] === 'NI' && (! preg_match('/^(Inadequate|Excessive) (.{1,50}) Intake$/u', $label))) {
                throw new \RuntimeException('Use the supported intake direction and nutrient format.');
            }
            if (preg_match('/^(diabetes(?: mellitus)?|hypertension|cancer|chronic kidney disease|heart failure)$/i', $label)) {
                throw new \RuntimeException('Medical diagnoses cannot replace nutrition problems.');
            }
            if (in_array($canonical, $problems, true)) {
                continue;
            }
            $keys = $draft['evidence_used'] ?? null;
            if (! is_array($keys) || $keys === [] || count($keys) > 8 || ! is_array($draft['evidence_quotes'] ?? null)) {
                throw new \RuntimeException('The AI returned a PES draft without bounded evidence.');
            }
            $refs = [];
            foreach ($keys as $key) {
                if (! is_string($key) || ! array_key_exists($key, $evidence) || in_array($key, ['existing_problem_keys', 'rnd_summary'], true)) {
                    throw new \RuntimeException('The AI returned evidence that was not supplied.');
                }
                $value = $evidence[$key];
                $quote = $draft['evidence_quotes'][$key] ?? null;
                $serialized = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
                if (! is_string($quote) || trim($quote) === '' || mb_strlen($quote) > 180 || ! str_contains(mb_strtolower($serialized), mb_strtolower($quote))) {
                    throw new \RuntimeException('The AI returned an evidence excerpt not present in the Assessment.');
                }
                $refs[] = ['key' => $key, 'value_hash' => hash('sha256', json_encode($value)), 'quote' => $quote];
            }
            $roles = [];
            foreach (['etiology_evidence', 'signs_evidence'] as $role) {
                $roleKeys = $draft[$role] ?? null;
                if (! is_array($roleKeys) || $roleKeys === [] || count($roleKeys) > 8 || count(array_filter($roleKeys, 'is_string')) !== count($roleKeys) || array_diff($roleKeys, $keys) !== []) {
                    throw new \RuntimeException('The AI did not ground both the cause and signs in supplied evidence.');
                }
                $roles[$role] = $roleKeys;
            }
            $causes = array_filter($roles['etiology_evidence'], fn ($key): bool => $this->eligibility->hasConcern($evidence[$key])
                && $this->eligibility->hasConcern($draft['evidence_quotes'][$key])
                && in_array($key, ['appetite', 'dietary_intake', 'present_diet', 'knowledge_notes', 'lifestyle', 'social_history', 'medical_history', 'physical_assessment', 'body_composition', 'food_intolerance', 'nutrient_drug_interaction', 'dietary_restrictions', 'supplements', 'chewing_swallowing_difficulties', 'gi_symptoms', 'energy_intake_status'], true)
            );
            if ($causes === []) {
                throw new \RuntimeException('The AI inferred a cause without a documented concern.');
            }
            $signConcerns = array_filter($roles['signs_evidence'], function ($key) use ($evidence, $draft): bool {
                return match ($key) {
                    'bmi' => in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true)
                        && (($evidence['bmi'] ?? 0) >= 25 || (($evidence['bmi'] ?? 0) > 0 && $evidence['bmi'] < (($evidence['age_70_plus'] ?? false) ? 20 : 18.5))),
                    'weight_loss_percentage', 'weight_change_percentage' => ($evidence['weight_change_direction'] ?? 'unknown') === 'loss' && ($evidence[$key] ?? 0) > 0,
                    'laboratory_flags' => ! empty($evidence[$key]),
                    'age_group', 'age_70_plus', 'sex', 'weight_kg', 'usual_weight_kg', 'height_cm', 'weight_change_direction', 'weight_change_period', 'pregnancy_lactation_status', 'physical_activity_level', 'functional_assessment', 'medication_count', 'laboratory_values', 'nutritional_status', 'ibw_percentage', 'muac_mm', 'waist_cm', 'hip_cm' => false,
                    default => $this->eligibility->hasConcern($evidence[$key]) && $this->eligibility->hasConcern($draft['evidence_quotes'][$key]),
                };
            });
            if ($signConcerns === []) {
                throw new \RuntimeException('The AI used only normal or contextual values as proof of a problem.');
            }
            $etiology = $this->component($draft['etiology'] ?? null, 75);
            $signs = $this->component($draft['signs'] ?? null, 75);
            if (! $this->groundedWording($etiology, $roles['etiology_evidence'], $evidence)
                || ! $this->groundedWording($signs, $roles['signs_evidence'], $evidence)) {
                throw new \RuntimeException('The AI wording adds claims not supported by its cited findings.');
            }
            $notes = [];
            foreach (['extra_notes', 'etiology_notes', 'sign_notes'] as $field) {
                $notes[$field] = $this->component($draft[$field] ?? '', 100, true);
            }
            foreach ([$label, $etiology, $signs] as $wording) {
                preg_match_all('/\d+(?:\.\d+)?/', $wording, $numbers);
                foreach ($numbers[0] as $number) {
                    if (! preg_match('/(?<!\d)'.preg_quote($number, '/').'(?!\d)/', json_encode(array_intersect_key($evidence, array_fill_keys($keys, true))))) {
                        throw new \RuntimeException('The AI added a measurement or threshold not present in its evidence.');
                    }
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
            $problems[] = $canonical;
            $builder = DiagnosisBuilder::fromDraft($candidate['domain'], $label, $etiology, $signs, $notes);
            $validated[] = [
                'draft_id' => (string) Str::uuid(), 'candidate_id' => $id, 'domain' => $candidate['domain'],
                'label' => $label, 'etiology' => $etiology, 'signs' => $signs,
                'builder_data' => $builder, ...$notes, 'evidence_refs' => $refs,
                'context_hash' => $this->contextHash($id, $evidence, array_column($refs, 'key')),
                'evidence_used' => array_map(fn ($key) => $this->formatEvidence($key, $evidence[$key]), array_values(array_unique($keys))),
                'source' => $candidate['source'],
            ];
        }
        $balanced = [];
        foreach (['NI', 'NC', 'NB'] as $domain) {
            foreach ($validated as $index => $draft) {
                if ($draft['domain'] === $domain) {
                    $balanced[] = $draft;
                    unset($validated[$index]);
                    break;
                }
            }
        }

        return [...$balanced, ...array_values($validated)];
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
        if ((! $optional && $text === '') || mb_strlen($text) > $limit) {
            throw new \RuntimeException('The AI returned diagnosis text exceeding its field limit.');
        }

        return $text;
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

            return array_values(array_unique(array_diff(array_map(static fn ($word) => rtrim($word, 's'), $words[0]), $stop)));
        };
        $source = [];
        foreach ($keys as $key) {
            $source[$key] = $evidence[$key];
        }
        $words = $normalize($wording);
        $supported = $normalize(json_encode($source, JSON_UNESCAPED_UNICODE));

        return $words !== [] && count(array_intersect($words, $supported)) / count($words) >= 0.67;
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

    private function fingerprint(array $evidence): string
    {
        unset($evidence['existing_problem_keys']);
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
