<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => UserResource::collection(User::orderBy('name')->get())]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        $this->auditUser('created', $user, 'Admin user account created');

        return response()->json(['data' => new UserResource($user)], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => new UserResource($user)]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $this->auditUser('updated', $user, 'Admin user account updated');

        return response()->json(['data' => new UserResource($user)]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->auditUser('deleted', $user, 'Admin user account deleted');
        $user->delete();
        return response()->json(null, 204);
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);
        $user->tokens()->delete();
        $this->auditUser('password_reset', $user, 'Admin reset user password');

        return response()->json(['message' => 'Password reset.']);
    }

    private function auditUser(string $event, User $user, string $description): void
    {
        activity('audit')
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event($event)
            ->withProperties([
                'user_id' => $user->id,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ])
            ->log($description);
    }
}
