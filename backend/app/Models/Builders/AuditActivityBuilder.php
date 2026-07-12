<?php

namespace App\Models\Builders;

use App\Services\Audit\AuditHealthMonitor;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class AuditActivityBuilder extends Builder
{
    public function update(array $values): never
    {
        $this->refuse('update');
    }

    public function delete(): never
    {
        $this->refuse('delete');
    }

    public function forceDelete(): never
    {
        $this->refuse('delete');
    }

    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        $this->refuse('update');
    }

    public function updateOrInsert(array $attributes, array|callable $values = []): never
    {
        $this->refuse('update');
    }

    public function increment($column, $amount = 1, array $extra = []): never
    {
        $this->refuse('update');
    }

    public function decrement($column, $amount = 1, array $extra = []): never
    {
        $this->refuse('update');
    }

    public function incrementEach(array $columns, array $extra = []): never
    {
        $this->refuse('update');
    }

    public function decrementEach(array $columns, array $extra = []): never
    {
        $this->refuse('update');
    }

    private function refuse(string $operation): never
    {
        app(AuditHealthMonitor::class)->unauthorizedRowMutation($operation);

        throw new RuntimeException($operation === 'update'
            ? 'Audit events are immutable.'
            : 'Audit events may only be deleted by the retention service.');
    }
}
