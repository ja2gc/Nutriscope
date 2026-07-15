<?php

namespace App\Http\Controllers\Admin;

use App\Data\AuditHistoryDto;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditHistoryResource;
use App\Models\AuditActivity;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditHistoryController extends Controller
{
    public function __construct(
        private readonly AuditRevisionRegistry $registry,
        private readonly AuditEventPresenter $presenter,
    ) {}

    public function __invoke(Request $request, string $event): AuditHistoryResource
    {
        Gate::authorize('viewAny', AuditActivity::class);

        $activity = AuditActivity::query()
            ->auditOnly()
            ->where('public_id', $event)
            ->with([
                'revision',
                'causer' => function (MorphTo $relation): void {
                    $relation->constrain([
                        User::class => fn (Builder $query): Builder => $query
                            ->withTrashed()
                            ->select('id', 'uuid', 'name', 'first_name', 'last_name', 'role'),
                    ]);
                },
            ])
            ->firstOrFail();
        $revision = $activity->revision;
        abort_unless($revision !== null && $this->registry->supports($revision), 404);

        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);

        return new AuditHistoryResource(new AuditHistoryDto(
            id: $revision->public_id,
            event: $this->presenter->present($activity, $viewer),
            serializer: $this->registry->keyFor($revision),
            schemaVersion: $revision->schema_version,
            occurredAt: $revision->occurred_at->toISOString(),
            before: $this->registry->present($revision, $revision->before),
            after: $this->registry->present($revision, $revision->after),
        ));
    }
}
