<?php

namespace App\Services\Reports\Contracts;

/**
 * Declares a report type's natural BROWSE axis and how to enumerate the real
 * records that can be rendered for it. Keeps the generators render-only — they
 * turn params into a PDF; an InstanceSource turns "what can I browse?" into a list
 * of those params. See Spec 4 §3 (the axis problem).
 */
interface InstanceSource
{
    /** 'period' (year→month buckets), 'entity' (a record), or 'singleton' (now). */
    public function axis(): string;

    /**
     * Renderable instances, newest first — ONLY those that actually have data.
     * Optional 'year'/'month' (and entity filters) narrow the list.
     *
     * @param  array<string,mixed> $filters
     * @return array<int,array{key:string,label:string,params:array<string,mixed>,date:?string}>
     */
    public function instances(array $filters): array;

    /**
     * Whether the given render params correspond to real data — the 404 gate for
     * on-demand render (Spec 4 §6 / decision #3).
     *
     * @param  array<string,mixed> $params
     */
    public function hasData(array $params): bool;
}
