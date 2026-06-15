<?php

namespace App\Services\Reports\Instances;

use App\Services\Reports\Contracts\InstanceSource;

/**
 * Point-in-time axis: a single "current" instance with no params. Used by reports
 * that are a snapshot of now (e.g. inventory stock levels) and have no history axis.
 */
class SingletonInstanceSource implements InstanceSource
{
    public function __construct(private string $label = 'Current') {}

    public function axis(): string
    {
        return 'singleton';
    }

    public function instances(array $filters): array
    {
        return [[
            'key'    => 'current',
            'label'  => $this->label,
            'params' => [],
            'date'   => null,
        ]];
    }

    public function hasData(array $params): bool
    {
        return true;
    }
}
