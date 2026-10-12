<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAuditLogsRequest;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditActivity;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\AuditFilterMetadata;
use App\Services\Audit\AuditQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditQuery $auditQuery,
        private readonly AuditEventPresenter $presenter,
        private readonly AuditFilterMetadata $filterMetadata,
    ) {}

    public function index(ListAuditLogsRequest $request): AnonymousResourceCollection
    {
        $this->authorizeCategories($request->filters());

        $logs = $this->auditQuery->build($request->filters())
            ->paginate($request->integer('per_page', 10));
        $logs->setCollection($logs->getCollection()->map(
            fn (AuditActivity $activity) => $this->presenter->present($activity, $request->user()),
        ));

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
}
