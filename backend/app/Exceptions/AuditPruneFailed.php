<?php

namespace App\Exceptions;

use RuntimeException;

class AuditPruneFailed extends RuntimeException
{
    /**
     * @param  array{eligible_count: int, deleted_count: int, held_category_count: int}  $progress
     */
    public function __construct(private readonly array $progress)
    {
        parent::__construct('Audit pruning failed.');
    }

    /** @return array{eligible_count: int, deleted_count: int, held_category_count: int} */
    public function progress(): array
    {
        return $this->progress;
    }
}
