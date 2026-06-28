<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            $this->auditAuth($request, 'login_failed', null, 'Login failed');

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = $request->user() ?? User::where('email', $request->email)->first();

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        // Platform-based role gating: the FSS mobile app is for Food Service staff
        // only; the web console is for RND and Admin only.
        $isApp = $request->validated('platform') === 'app';

        if ($isApp && ! $user->isFss()) {
            return response()->json(['message' => 'This app is for Food Service staff only.'], 403);
        }

        if (! $isApp && $user->isFss()) {
            return response()->json(['message' => 'Food Service staff must sign in through the mobile app.'], 403);
        }

        // Revoke only tokens for this device, then issue a fresh one
        $tokenName = $request->validated()['device_name'] ?? 'nutriscope-token';
        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName, [$user->role])->plainTextToken;

        $this->auditAuth($request, 'login', $user, 'User logged in', [
            'platform' => $request->validated('platform') ?? 'web',
            'device_name' => $tokenName,
        ]);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->auditAuth($request, 'logout', $user, 'User logged out');

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    /**
     * PATCH /api/auth/profile — self-service name/email update (rnd.md §9).
     * `name` is the same field used as the report "prepared by".
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json(new UserResource($user->fresh()));
    }

    /**
     * POST /api/auth/password — self-service password change (rnd.md §9).
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated()['password']),
        ]);
        $user->tokens()->delete();

        $this->auditAuth($request, 'password_changed', $user, 'Password changed');

        return response()->json(['message' => 'Password updated.']);
    }

    private function auditAuth(
        Request $request,
        string $event,
        ?User $user,
        string $description,
        array $properties = [],
    ): Activity {
        $activity = activity('audit');

        if ($user) {
            $activity->causedBy($user);
        }

        return $activity->event($event)
            ->withProperties(array_merge([
                'email' => $request->string('email')->lower()->toString() ?: null,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ], $properties))
            ->log($description);
    }
}
