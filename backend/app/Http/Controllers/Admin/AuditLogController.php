<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAuditLogsRequest;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditActivity;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\AuditFilterMetadata;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditQuery $auditQuery,
        private readonly AuditEventPresenter $presenter,
        private readonly AuditFilterMetadata $filterMetadata,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(ListAuditLogsRequest $request): AnonymousResourceCollection
    {
        $this->authorizeCategories($request->filters());

        $logs = $this->auditQuery->build($request->filters())
            ->paginate($request->integer('per_page', 25));
        $logs->setCollection($logs->getCollection()->map(
            fn (AuditActivity $activity) => $this->presenter->present($activity),
        ));

        $this->recordListAccess($request);

        return AuditEventResource::collection($logs)->additional([
            'meta' => $this->filterMetadata->for($request->user()),
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function authorizeCategories(array $filters): void
    {
        if (! isset($filters['category']) || $filters['category'] === AuditCategory::Clinical->value) {
            Gate::authorize('viewClinical', AuditActivity::class);
        }

        if (! isset($filters['category']) || $filters['category'] === AuditCategory::Security->value) {
            Gate::authorize('viewSecurity', AuditActivity::class);
        }
    }

    private function recordListAccess(ListAuditLogsRequest $request): void
    {
        $actor = $request->user();
        $ttl = (int) config('audit.deduplication.audit_list_view_seconds', 15 * 60);
        if ($actor === null || ! Cache::add('audit-list-view:'.$actor->getAuthIdentifier(), true, $ttl)) {
            return;
        }

        try {
            $this->auditLogger->record(
                AuditAction::AuditLogViewed,
                AuditCategory::Security,
                AuditDomain::System,
                details: ['status' => 200],
                actor: $actor,
            );
        } catch (\Throwable $exception) {
            Cache::forget('audit-list-view:'.$actor->getAuthIdentifier());

            throw $exception;
        }
    }
}
