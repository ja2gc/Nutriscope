<?php

use App\Models\Intervention;
use App\Models\Report;
use App\Support\InterventionGuidance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Exact PatientSeeder template fingerprints before the reviewed guidance change. */
    private const LEGACY = [
        'education_notes' => ['d811812b4a6ba8367eda3e9a1d97e47cd46b1f5369339648cab34667280ef059', '87d2a045333ee7d4b7d4d0c37b0ef21b88e5445e69188b750830cfb566743d78'],
        'counseling_goals' => ['9dd339a43d900057397438323d6a7bd1c6b7d1134005ce92268bac2aed92d182', '4fb46fc3c3df4f044ec104925e9faaed8e94851171734440d79c3fde785db92f'],
        'strategies' => ['add5a1c8a167aa3fdcdca10a6a9c3ab463ef9e1e4ad84d5f4eaac0a33025ff29', 'e746b58706241a7ccfec35fa3b1a0b95c9e050a6421645fbe78ec870d459be3c'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Intervention::query()->where(function ($query): void {
            foreach (self::LEGACY as $field => $hashes) {
                $query->orWhereRaw('SHA2('.$field.', 256) IN (?, ?)', $hashes);
            }
        })->chunkById(50, function ($rows): void {
            DB::transaction(function () use ($rows): void {
                $plans = Intervention::query()->whereKey($rows->modelKeys())->with(['ncpRecord', 'mealPlans'])->lockForUpdate()->get();
                $originals = json_encode(DB::table('interventions')->whereIn('id', $plans->modelKeys())->get()->all(), JSON_THROW_ON_ERROR);
                $disk = Storage::disk(config('filesystems.private_uploads', 'private_uploads'));
                $backup = 'maintenance/intervention-guidance-'.hash('sha256', $originals).'.enc';
                if (! $disk->exists($backup) && ! $disk->put($backup, Crypt::encryptString($originals))) {
                    throw new RuntimeException('Guidance backup failed; no notes changed.');
                }
                if (Crypt::decryptString($disk->get($backup)) !== $originals) {
                    throw new RuntimeException('Guidance backup verification failed; no notes changed.');
                }
                foreach ($plans as $plan) {
                    $inputs = $plan->calculation_snapshot['inputs'] ?? [];
                    $guidance = InterventionGuidance::for($plan->goal_type ?? 'custom', $plan->disease_stage, [
                        ...($plan->calculation_options ?? []), 'fluid_mode' => $plan->fluid_mode,
                        'energy_adjustment_mode' => empty($plan->calculation_snapshot['manual_overrides']['energy_kcal']) ? ($plan->energy_adjustment_mode ?? 'none') : 'none',
                        'pediatric' => ($inputs['ageYears'] ?? 18) < 18,
                        'age_months' => $inputs['ageMonths'] ?? (($inputs['ageYears'] ?? 18) * 12),
                        'maternal' => ! in_array($inputs['pregnancyLactationStatus'] ?? 'none', [null, 'none'], true),
                    ]);
                    $changes = [];
                    foreach (self::LEGACY as $field => $hashes) {
                        if (in_array(hash('sha256', (string) $plan->getAttribute($field)), $hashes, true)) {
                            $changes[$field] = $guidance[$field];
                        }
                    }
                    if ($changes !== []) {
                        DB::table('interventions')->where('id', $plan->id)->update($changes);
                    }
                }
                $references = [
                    'ncp_record_id' => [...$plans->pluck('ncp_record_id')->all(), ...$plans->pluck('ncpRecord.uuid')->all()],
                    'intervention_plan_id' => [...$plans->modelKeys(), ...$plans->pluck('uuid')->all()],
                    'meal_plan_id' => [...$plans->flatMap->mealPlans->pluck('id')->all(), ...$plans->flatMap->mealPlans->pluck('uuid')->all()],
                ];
                Report::query()->whereIn('type', ['patient_menu_plan', 'ncp_summary'])->where('status', '!=', 'archived')
                    ->where(function ($query) use ($plans, $references): void {
                        $query->whereIn('audit_ncp_record_id', $plans->pluck('ncp_record_id')->all());
                        foreach ($references as $key => $values) {
                            foreach (array_unique($values) as $value) {
                                $query->orWhere('parameters->'.$key, (string) $value);
                            }
                        }
                    })->update(['cache_expires_at' => now(), 'expires_at' => now()]);
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Forward-only correction; exact originals are retained in encrypted private storage.
    }
};
