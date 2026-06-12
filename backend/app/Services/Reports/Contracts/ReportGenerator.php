<?php

namespace App\Services\Reports\Contracts;

use App\Models\Report;

/**
 * A report generator turns a persisted {@see Report} (type + parameters) into the
 * Blade view name + view data that {@see \App\Services\Reports\ReportService}
 * renders to PDF. Generators never touch DomPDF or storage — they only build data.
 */
interface ReportGenerator
{
    /** The reports.type value this generator handles. */
    public function type(): string;

    /** Blade view (e.g. "reports.program-project-activity"). */
    public function view(): string;

    /** Paper size + orientation, e.g. ['a4', 'portrait']. */
    public function paper(): array;

    /** View data (excluding shared branding/signatories the service injects). */
    public function data(Report $report): array;
}
