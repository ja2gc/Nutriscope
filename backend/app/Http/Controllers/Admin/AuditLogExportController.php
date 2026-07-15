<?php

namespace App\Http\Controllers\Admin;

use App\Data\AuditEventDto;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAuditLogsRequest;
use App\Models\AuditActivity;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditQuery;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogExportController extends Controller
{
    private const MAX_ROWS = 50_000;

    public function __construct(
        private readonly AuditQuery $auditQuery,
        private readonly AuditEventPresenter $presenter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function __invoke(ListAuditLogsRequest $request): StreamedResponse
    {
        abort_unless(config('audit.features.export'), 404);
        Gate::authorize('export', AuditActivity::class);
        if (! isset($request->filters()['category']) || $request->filters()['category'] === AuditCategory::Clinical->value) {
            Gate::authorize('viewClinical', AuditActivity::class);
        }
        if (! isset($request->filters()['category']) || $request->filters()['category'] === AuditCategory::Security->value) {
            Gate::authorize('viewSecurity', AuditActivity::class);
        }

        $query = $this->auditQuery->build($request->filters());
        $lastExportedId = (int) ((clone $query)->reorder()->max('id') ?? 0);

        $this->auditLogger->record(
            AuditAction::Exported,
            AuditCategory::Security,
            AuditDomain::System,
            details: ['format' => 'csv'],
            actor: $request->user(),
        );

        return response()->streamDownload(function () use ($query, $lastExportedId): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fputcsv($stream, [
                'event_reference', 'category', 'domain', 'action', 'action_label', 'summary', 'severity', 'outcome',
                'actor_id', 'actor_kind', 'actor_name', 'actor_role',
                'subject_type', 'subject_id', 'subject_label',
                'context_type', 'context_id', 'context_label', 'occurred_at', 'details', 'changes',
            ], escape: '');

            $rows = 0;
            foreach ($query->where('id', '<=', $lastExportedId)->lazy(500) as $activity) {
                if ($rows++ >= $this->maxRows()) {
                    break;
                }
                $event = $this->presenter->present($activity);
                fputcsv($stream, $this->csvRow($event), escape: '');
            }

            fclose($stream);
        }, 'audit-events-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return list<string|int|float|null> */
    private function csvRow(AuditEventDto $event): array
    {
        return array_map(fn (mixed $value): mixed => $this->csvCell($value), [
            $event->id, $event->category, $event->domain, $event->action, $event->actionLabel,
            $event->summary, $event->severity, $event->outcome,
            $event->actor['id'] ?? null, $event->actor['kind'] ?? null,
            $event->actor['name'] ?? null, $event->actor['role'] ?? null,
            $event->subject['type'] ?? null, $event->subject['id'] ?? null, $event->subject['label'] ?? null,
            $event->context['type'] ?? null, $event->context['id'] ?? null, $event->context['label'] ?? null,
            $event->occurredAt,
            collect($event->details)->map(
                fn (array $detail): string => $detail['label'].': '.$this->csvValue($detail['value']->value),
            )->implode('; '),
            collect($event->changes)->pluck('label')->implode('; '),
        ]);
    }

    private function csvValue(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)->filter(fn (mixed $item): bool => is_scalar($item))->implode('|');
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function maxRows(): int
    {
        return min(self::MAX_ROWS, max(1, (int) config('audit.export.max_rows', self::MAX_ROWS)));
    }

    private function csvCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
