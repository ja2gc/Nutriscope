<?php

namespace App\Http\Requests\Concerns;

trait HasPaginationRules
{
    public const DEFAULT_PER_PAGE = 10;

    public const MAX_PER_PAGE = 10;

    /** @return array<string, list<string>> */
    protected function paginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
