<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAuditActorsRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AuditActorController extends Controller
{
    public function __invoke(ListAuditActorsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $actors = User::query()
            ->withTrashed()
            ->select('id', 'uuid', 'name', 'first_name', 'last_name', 'role')
            ->when($validated['selected_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('uuid', $id))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function (Builder $scope) use ($term): void {
                    $scope->where('name', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) LIKE ?", [$term]);
                });
            })
            ->orderByRaw("LOWER(TRIM(COALESCE(NULLIF(last_name, ''), name)))")
            ->orderByRaw("LOWER(TRIM(COALESCE(NULLIF(first_name, ''), name)))")
            ->orderBy('id')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $actors->getCollection()->map(fn (User $user): array => [
                'id' => $user->uuid,
                'name' => $user->display_name,
                'role' => $user->role,
            ])->values(),
            'meta' => [
                'current_page' => $actors->currentPage(),
                'last_page' => $actors->lastPage(),
                'per_page' => $actors->perPage(),
                'total' => $actors->total(),
            ],
        ]);
    }
}
