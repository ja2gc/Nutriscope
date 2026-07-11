<?php

namespace App\Http\Controllers\Admin;

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
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => UserResource::collection(User::orderBy('name')->get())]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        $this->auditUser(AuditAction::Created, $user);

        return response()->json(['data' => new UserResource($user)], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => new UserResource($user)]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $this->auditUser(AuditAction::Updated, $user);

        return response()->json(['data' => new UserResource($user)]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->auditUser(AuditAction::Deleted, $user);
        $user->delete();

        return response()->json(null, 204);
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);
        $user->tokens()->delete();
        $this->auditUser(AuditAction::PasswordReset, $user);

        return response()->json(['message' => 'Password reset.']);
    }

    private function auditUser(AuditAction $action, User $user): void
    {
        $this->auditLogger->record(
            $action,
            AuditCategory::Security,
            AuditDomain::Accounts,
            subject: $user,
            details: [
                'user_id' => $user->id,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
            actor: auth()->user(),
        );
    }
}
