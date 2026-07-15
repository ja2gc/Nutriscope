<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Identity\SynchronizePersonName;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SynchronizePersonName $synchronizePersonName,
    ) {}

    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderByRaw("LOWER(TRIM(COALESCE(NULLIF(last_name, ''), name)))")
            ->orderByRaw("LOWER(TRIM(COALESCE(NULLIF(first_name, ''), name)))")
            ->orderBy('id')
            ->get();

        return response()->json(['data' => UserResource::collection($users)]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $this->synchronizePersonName->forCreate($request->validated());
        $data['password'] = Hash::make($data['password']);

        $this->auditLogger->assertAvailable();
        $user = DB::transaction(function () use ($data): User {
            $user = User::create($data);
            $changedFields = ['first_name', 'is_active', 'last_name', 'name', 'role'];
            $this->auditUser(
                AuditAction::Created,
                $user,
                $changedFields,
                newValues: array_intersect_key($this->accountAuditValues($user), array_flip($changedFields)),
            );

            return $user;
        });

        return response()->json(['data' => new UserResource($user)], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => new UserResource($user)]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $this->synchronizePersonName->forUpdate($user, $request->validated());
        $passwordChanged = ! empty($data['password']);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($data, $passwordChanged, $user): void {
            $oldValues = $this->accountAuditValues($user);
            $user->fill($data);
            $changedFields = array_values(array_diff(array_keys($user->getDirty()), ['password', 'updated_at']));
            if ($passwordChanged) {
                $changedFields[] = 'password';
            }
            sort($changedFields);
            $user->save();

            if ($passwordChanged || in_array('role', $changedFields, true) || in_array('is_active', $changedFields, true)) {
                $user->tokens()->delete();
            }

            if ($changedFields !== []) {
                $action = $changedFields === ['password']
                    ? AuditAction::PasswordReset
                    : AuditAction::Updated;
                $this->auditUser(
                    $action,
                    $user,
                    $changedFields,
                    array_intersect_key($oldValues, array_flip($changedFields)),
                    array_intersect_key($this->accountAuditValues($user), array_flip($changedFields)),
                );
            }
        });

        return response()->json(['data' => new UserResource($user)]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($user): void {
            $oldValues = $this->accountAuditValues($user);
            $user->forceFill(['is_active' => false])->save();
            $user->tokens()->delete();
            $user->delete();
            $this->auditUser(
                AuditAction::Deleted,
                $user,
                ['is_active'],
                ['is_active' => $oldValues['is_active']],
                ['is_active' => (bool) $user->is_active],
            );
        });

        return response()->json(null, 204);
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($request, $user): void {
            $user->update([
                'password' => Hash::make($request->validated('password')),
            ]);
            $user->tokens()->delete();
            $this->auditUser(AuditAction::PasswordReset, $user);
        });

        return response()->json(['message' => 'Password reset.']);
    }

    private function auditUser(
        AuditAction $action,
        User $user,
        array $changedFields = [],
        array $oldValues = [],
        array $newValues = [],
    ): void {
        $this->auditLogger->record(
            $action,
            AuditCategory::Security,
            AuditDomain::Accounts,
            subject: $user,
            details: [
                'public_id' => $user->uuid,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'changed_fields' => $changedFields,
            ],
            actor: auth()->user(),
            includeRequestMetadata: false,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }

    /** @return array{name: string, first_name: ?string, last_name: ?string, role: string, is_active: bool} */
    private function accountAuditValues(User $user): array
    {
        return [
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
        ];
    }
}
