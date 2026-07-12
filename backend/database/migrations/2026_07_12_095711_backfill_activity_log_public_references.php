<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

return new class extends Migration
{
    public function up(): void
    {
        $this->query()
            ->select('id', 'properties', 'subject_public_id', 'context_public_id')
            ->where(function (Builder $query): void {
                $query->whereNull('subject_public_id')->orWhereNull('context_public_id');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $batch = [];

                foreach ($rows as $row) {
                    $properties = is_string($row->properties) ? json_decode($row->properties, true) : null;
                    $details = is_array($properties['details'] ?? null) ? $properties['details'] : [];
                    $update = [];

                    if ($row->subject_public_id === null) {
                        $update['subject_public_id'] = $this->firstUuid($details, [
                            'subject_public_id', 'public_id', 'report_public_id',
                        ]);
                    }
                    if ($row->context_public_id === null) {
                        $update['context_public_id'] = $this->firstUuid($details, ['context_public_id']);
                    }

                    $update = array_filter($update);
                    if ($update !== []) {
                        $update['id'] = (int) $row->id;
                        $batch[] = $update;
                    }
                }

                $this->bulkUpdate($batch);
            });
    }

    public function down(): void
    {
        // Intentionally retained; the following DDL rollback drops the columns.
    }

    private function query(): Builder
    {
        return DB::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'));
    }

    /** @param list<array{id: int, subject_public_id?: string, context_public_id?: string}> $updates */
    private function bulkUpdate(array $updates): void
    {
        if ($updates === []) {
            return;
        }

        $connection = DB::connection(config('activitylog.database_connection'));
        $table = $connection->getQueryGrammar()->wrapTable(config('activitylog.table_name'));
        $assignments = [];
        $bindings = [];

        foreach (['subject_public_id', 'context_public_id'] as $column) {
            $cases = [];
            foreach ($updates as $update) {
                if (! isset($update[$column])) {
                    continue;
                }
                $wrapped = $connection->getQueryGrammar()->wrap($column);
                $cases[] = "WHEN ? THEN COALESCE({$wrapped}, ?)";
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

    /** @param list<string> $keys */
    private function firstUuid(array $details, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $details[$key] ?? null;
            if (is_string($value) && Uuid::isValid($value)) {
                return strtolower($value);
            }
        }

        return null;
    }
};
