<?php

namespace App\Services\Audit;

use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Models\AiUsageLimit;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AuditSetting;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\Diagnosis;
use App\Models\DietListCount;
use App\Models\FoodItem;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceSetting;
use App\Models\FsItem;
use App\Models\Intervention;
use App\Models\Inventory;
use App\Models\MealPlan;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleTemplate;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemCorrection;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\Recipe;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Models\ScreeningDocument;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Sop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class AuditOversightBackfill
{
    private const NUTRITION_LIBRARY_TYPES = [FoodItem::class, Recipe::class];

    private const CLINICAL_TYPES = [
        Patient::class,
        NcpRecord::class,
        Assessment::class,
        Diagnosis::class,
        Intervention::class,
        Monitoring::class,
        MealPlan::class,
        ScreeningDocument::class,
    ];

    private const REPORT_TYPES = [Report::class, ReportBranding::class, ReportTemplate::class];

    private const FOOD_SERVICE_TYPES = [
        Budget::class,
        BudgetLedger::class,
        DietListCount::class,
        FoodServiceRecipe::class,
        FoodServiceSetting::class,
        FsItem::class,
        Inventory::class,
        MealPrepLog::class,
        MenuCycle::class,
        MenuCycleTemplate::class,
        PurchaseOrder::class,
        PurchaseOrderAttachment::class,
        PurchaseOrderItem::class,
        PurchaseOrderItemCorrection::class,
        PurchaseOrderVendorGroup::class,
        ShoppingList::class,
        ShoppingListItem::class,
        Supplier::class,
    ];

    private const SECURITY_ADMINISTRATION_TYPES = [
        AiUsageLimit::class,
        Announcement::class,
        AuditSetting::class,
        Sop::class,
        User::class,
    ];

    /** @return array{scanned: int, updated: int, modules: int, domains: int, patient_snapshots: int} */
    public function run(Connection $connection, int $chunkSize = 500): array
    {
        $stats = ['scanned' => 0, 'updated' => 0, 'modules' => 0, 'domains' => 0, 'patient_snapshots' => 0];
        $schema = $connection->getSchemaBuilder();

        $connection->table(config('activitylog.table_name'))
            ->where('log_name', config('audit.log_name'))
            ->select([
                'id', 'module', 'patient_display_name_snapshot', 'category', 'domain',
                'subject_type', 'subject_id', 'context_type', 'context_id',
                'root_patient_id', 'ncp_record_id',
            ])
            ->orderBy('id')
            ->chunkById(max(1, min($chunkSize, 5000)), function (Collection $rows) use ($connection, $schema, &$stats): void {
                $stats['scanned'] += $rows->count();
                $patientIds = $this->patientIdsByActivity($connection, $schema, $rows);
                $displayNames = $this->patientDisplayNames($connection, $schema, array_values(array_unique($patientIds)));
                $updates = [];

                foreach ($rows as $row) {
                    $update = [];
                    $nutritionLibraryType = $this->isNutritionLibraryType($row->subject_type);
                    $needsModule = $row->module === null
                        || ($nutritionLibraryType && $row->module === AuditModule::FoodServiceOperations->value);
                    if ($needsModule && ($module = $this->moduleFor($row)) !== null) {
                        $update['module'] = $module->value;
                        $stats['modules']++;
                    }
                    if ($nutritionLibraryType
                        && in_array($row->domain, [null, AuditDomain::FoodService->value], true)) {
                        $update['domain'] = AuditDomain::NutritionLibrary->value;
                        $stats['domains']++;
                    }
                    $patientId = $patientIds[(int) $row->id] ?? null;
                    if ($row->patient_display_name_snapshot === null
                        && $patientId !== null
                        && $this->isPatientLinked($row)
                        && isset($displayNames[$patientId])) {
                        $update['patient_display_name_snapshot'] = Crypt::encryptString($displayNames[$patientId]);
                        $stats['patient_snapshots']++;
                    }
                    if ($update !== []) {
                        $updates[] = ['id' => (int) $row->id, ...$update];
                    }
                }

                $stats['updated'] += count($updates);
                $this->bulkUpdate($connection, $updates);
            });

        return $stats;
    }

    private function moduleFor(object $row): ?AuditModule
    {
        if ($this->isNutritionLibraryType($row->subject_type)) {
            return AuditModule::NutritionCare;
        }
        if ($this->isTypeIn($row->subject_type, self::REPORT_TYPES)
            || $row->domain === AuditDomain::Reports->value) {
            return AuditModule::Reports;
        }
        if ($this->isTypeIn($row->subject_type, self::CLINICAL_TYPES)
            || $row->category === AuditCategory::Clinical->value
            || in_array($row->domain, [AuditDomain::Patients->value, AuditDomain::Ncp->value], true)) {
            return AuditModule::NutritionCare;
        }
        if ($this->isTypeIn($row->subject_type, self::FOOD_SERVICE_TYPES)
            || in_array($row->domain, [
                AuditDomain::Budget->value,
                AuditDomain::Procurement->value,
                AuditDomain::FoodService->value,
            ], true)) {
            return AuditModule::FoodServiceOperations;
        }
        if ($this->isTypeIn($row->subject_type, self::SECURITY_ADMINISTRATION_TYPES)
            || $row->category === AuditCategory::Security->value
            || in_array($row->domain, [AuditDomain::Accounts->value, AuditDomain::System->value], true)) {
            return AuditModule::SecurityAdministration;
        }

        return null;
    }

    private function isPatientLinked(object $row): bool
    {
        return $row->root_patient_id !== null
            || $row->ncp_record_id !== null
            || $row->category === AuditCategory::Clinical->value
            || in_array($row->domain, [
                AuditDomain::Patients->value,
                AuditDomain::Ncp->value,
                AuditDomain::Reports->value,
            ], true)
            || $this->isTypeIn($row->subject_type, [...self::CLINICAL_TYPES, Report::class]);
    }

    /** @return array<int, int> keyed by activity id */
    private function patientIdsByActivity(Connection $connection, Builder $schema, Collection $rows): array
    {
        $patientIds = [];
        $ncpIds = [];

        foreach ($rows as $row) {
            $activityId = (int) $row->id;
            if ($this->positiveInt($row->root_patient_id) !== null) {
                $patientIds[$activityId] = (int) $row->root_patient_id;
            }
            if ($this->positiveInt($row->ncp_record_id) !== null) {
                $ncpIds[$activityId] = (int) $row->ncp_record_id;
            }
            foreach ([['type' => $row->subject_type, 'id' => $row->subject_id], ['type' => $row->context_type, 'id' => $row->context_id]] as $reference) {
                if ($this->typeIs($reference['type'], Patient::class) && $this->positiveInt($reference['id']) !== null) {
                    $patientIds[$activityId] ??= (int) $reference['id'];
                }
                if ($this->typeIs($reference['type'], NcpRecord::class) && $this->positiveInt($reference['id']) !== null) {
                    $ncpIds[$activityId] ??= (int) $reference['id'];
                }
            }
        }

        $this->resolveReports($connection, $schema, $rows, $patientIds, $ncpIds);
        $this->resolveNcpChildren($connection, $schema, $rows, $ncpIds);
        $this->resolveScreeningDocuments($connection, $schema, $rows, $patientIds, $ncpIds);
        $this->resolveMealPlans($connection, $schema, $rows, $patientIds, $ncpIds);

        if ($ncpIds !== [] && $schema->hasTable('ncp_records')) {
            $patientsByNcp = $connection->table('ncp_records')
                ->whereIn('id', array_values(array_unique($ncpIds)))
                ->pluck('patient_id', 'id');
            foreach ($ncpIds as $activityId => $ncpId) {
                $patientId = $this->positiveInt($patientsByNcp[$ncpId] ?? null);
                if ($patientId !== null) {
                    $patientIds[$activityId] ??= $patientId;
                }
            }
        }

        return $patientIds;
    }

    private function resolveReports(
        Connection $connection,
        Builder $schema,
        Collection $rows,
        array &$patientIds,
        array &$ncpIds,
    ): void {
        if (! $schema->hasTable('reports')) {
            return;
        }
        $activityByReport = $this->activityIdsByTypedReference($rows, Report::class);
        if ($activityByReport === []) {
            return;
        }
        $reports = $connection->table('reports')
            ->whereIn('id', array_keys($activityByReport))
            ->get(['id', 'audit_patient_id', 'audit_ncp_record_id'])
            ->keyBy('id');
        foreach ($activityByReport as $reportId => $activityIds) {
            $report = $reports->get($reportId);
            foreach ($activityIds as $activityId) {
                if (($patientId = $this->positiveInt($report?->audit_patient_id)) !== null) {
                    $patientIds[$activityId] ??= $patientId;
                }
                if (($ncpId = $this->positiveInt($report?->audit_ncp_record_id)) !== null) {
                    $ncpIds[$activityId] ??= $ncpId;
                }
            }
        }
    }

    private function resolveNcpChildren(
        Connection $connection,
        Builder $schema,
        Collection $rows,
        array &$ncpIds,
    ): void {
        foreach ([
            Assessment::class => 'assessments',
            Diagnosis::class => 'diagnoses',
            Intervention::class => 'interventions',
            Monitoring::class => 'monitorings',
        ] as $type => $table) {
            if (! $schema->hasTable($table)) {
                continue;
            }
            $activityBySubject = $this->activityIdsByTypedReference($rows, $type);
            if ($activityBySubject === []) {
                continue;
            }
            $ncpBySubject = $connection->table($table)
                ->whereIn('id', array_keys($activityBySubject))
                ->pluck('ncp_record_id', 'id');
            foreach ($activityBySubject as $subjectId => $activityIds) {
                $ncpId = $this->positiveInt($ncpBySubject[$subjectId] ?? null);
                foreach ($activityIds as $activityId) {
                    if ($ncpId !== null) {
                        $ncpIds[$activityId] ??= $ncpId;
                    }
                }
            }
        }
    }

    private function resolveScreeningDocuments(
        Connection $connection,
        Builder $schema,
        Collection $rows,
        array &$patientIds,
        array &$ncpIds,
    ): void {
        if (! $schema->hasTable('screening_documents')) {
            return;
        }
        $activityBySubject = $this->activityIdsByTypedReference($rows, ScreeningDocument::class);
        if ($activityBySubject === []) {
            return;
        }
        $documents = $connection->table('screening_documents')
            ->whereIn('id', array_keys($activityBySubject))
            ->get(['id', 'patient_id', 'ncp_record_id'])
            ->keyBy('id');
        foreach ($activityBySubject as $subjectId => $activityIds) {
            $document = $documents->get($subjectId);
            foreach ($activityIds as $activityId) {
                if (($patientId = $this->positiveInt($document?->patient_id)) !== null) {
                    $patientIds[$activityId] ??= $patientId;
                }
                if (($ncpId = $this->positiveInt($document?->ncp_record_id)) !== null) {
                    $ncpIds[$activityId] ??= $ncpId;
                }
            }
        }
    }

    private function resolveMealPlans(
        Connection $connection,
        Builder $schema,
        Collection $rows,
        array &$patientIds,
        array &$ncpIds,
    ): void {
        if (! $schema->hasTable('meal_plans')) {
            return;
        }
        $activityBySubject = $this->activityIdsByTypedReference($rows, MealPlan::class);
        if ($activityBySubject === []) {
            return;
        }
        $plans = $connection->table('meal_plans')
            ->whereIn('id', array_keys($activityBySubject))
            ->get(['id', 'patient_id', 'intervention_id'])
            ->keyBy('id');
        $interventionIds = $plans->pluck('intervention_id')->filter()->unique()->values()->all();
        $ncpByIntervention = $interventionIds !== [] && $schema->hasTable('interventions')
            ? $connection->table('interventions')->whereIn('id', $interventionIds)->pluck('ncp_record_id', 'id')
            : collect();
        foreach ($activityBySubject as $subjectId => $activityIds) {
            $plan = $plans->get($subjectId);
            foreach ($activityIds as $activityId) {
                if (($patientId = $this->positiveInt($plan?->patient_id)) !== null) {
                    $patientIds[$activityId] ??= $patientId;
                }
                $ncpId = $this->positiveInt($ncpByIntervention[$plan?->intervention_id] ?? null);
                if ($ncpId !== null) {
                    $ncpIds[$activityId] ??= $ncpId;
                }
            }
        }
    }

    /** @return array<int, list<int>> keyed by referenced model id */
    private function activityIdsByTypedReference(Collection $rows, string $type): array
    {
        $result = [];
        foreach ($rows as $row) {
            foreach ([['type' => $row->subject_type, 'id' => $row->subject_id], ['type' => $row->context_type, 'id' => $row->context_id]] as $reference) {
                $id = $this->positiveInt($reference['id']);
                if ($id !== null && $this->typeIs($reference['type'], $type)) {
                    $result[$id][] = (int) $row->id;
                }
            }
        }

        return $result;
    }

    /** @param list<int> $ids
     * @return array<int, string>
     */
    private function patientDisplayNames(Connection $connection, Builder $schema, array $ids): array
    {
        if ($ids === [] || ! $schema->hasTable('patients')) {
            return [];
        }

        return $connection->table('patients')
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'first_name', 'last_name'])
            ->mapWithKeys(function (object $patient): array {
                $parts = array_values(array_filter([
                    trim((string) $patient->first_name),
                    trim((string) $patient->last_name),
                ], fn (string $part): bool => $part !== ''));
                $displayName = $parts !== [] ? implode(' ', $parts) : trim((string) $patient->name);

                return $displayName === '' ? [] : [(int) $patient->id => $displayName];
            })
            ->all();
    }

    /** @param list<array{id: int, module?: string, domain?: string, patient_display_name_snapshot?: string}> $updates */
    private function bulkUpdate(Connection $connection, array $updates): void
    {
        if ($updates === []) {
            return;
        }
        $grammar = $connection->getQueryGrammar();
        $table = $grammar->wrapTable(config('activitylog.table_name'));
        $assignments = [];
        $bindings = [];

        foreach (['module', 'domain', 'patient_display_name_snapshot'] as $column) {
            $cases = [];
            $wrapped = $grammar->wrap($column);
            foreach ($updates as $update) {
                if (! array_key_exists($column, $update)) {
                    continue;
                }
                $value = $column === 'patient_display_name_snapshot' ? "COALESCE({$wrapped}, ?)" : '?';
                $cases[] = "WHEN ? THEN {$value}";
                $bindings[] = $update['id'];
                $bindings[] = $update[$column];
            }
            if ($cases !== []) {
                $assignments[] = "{$wrapped} = CASE `id` ".implode(' ', $cases)." ELSE {$wrapped} END";
            }
        }
        if ($assignments === []) {
            return;
        }

        $ids = array_column($updates, 'id');
        $bindings = [...$bindings, ...$ids];
        $connection->update(
            "UPDATE {$table} SET ".implode(', ', $assignments).' WHERE `id` IN ('.implode(', ', array_fill(0, count($ids), '?')).')',
            $bindings,
        );
    }

    private function isNutritionLibraryType(?string $type): bool
    {
        return $this->isTypeIn($type, self::NUTRITION_LIBRARY_TYPES);
    }

    /** @param list<class-string> $types */
    private function isTypeIn(?string $type, array $types): bool
    {
        foreach ($types as $candidate) {
            if ($this->typeIs($type, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function typeIs(?string $stored, string $type): bool
    {
        return is_string($stored)
            && ($stored === $type || strtolower($stored) === strtolower(class_basename($type)));
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
