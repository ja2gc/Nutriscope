<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\SynchronizePersonName;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteOnboardingRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditActivity;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\StoredObjectStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SynchronizePersonName $synchronizePersonName,
        private readonly StoredObjectStorage $storedObjects,
    ) {}

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            $this->auditFailedLogin();

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = $request->user() ?? User::where('email', $request->email)->first();

        if (! $user->is_active) {
            $this->auditFailedLogin('Account inactive');
            Auth::logout();

            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        // Platform-based role gating: the FSS mobile app is for Food Service staff
        // only; the web console is for RND and Admin only.
        $isApp = $request->validated('platform') === 'app';

        if ($isApp && ! $user->isFss()) {
            $this->auditFailedLogin('Wrong sign-in platform');
            Auth::logout();

            return response()->json(['message' => 'This app is for Food Service staff only.'], 403);
        }

        if (! $isApp && $user->isFss()) {
            $this->auditFailedLogin('Wrong sign-in platform');
            Auth::logout();

            return response()->json(['message' => 'Food Service staff must sign in through the mobile app.'], 403);
        }

        // Revoke only tokens for this device, then issue a fresh one
        $tokenName = $request->validated()['device_name'] ?? 'nutriscope-token';
        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName, [$user->role])->plainTextToken;
        try {
            $this->auditLogger->record(
                AuditAction::Created,
                AuditCategory::Security,
                AuditDomain::Accounts,
                subject: $user,
                actor: $user,
                details: ['subject_label' => 'Session'],
            );
        } catch (Throwable) {
            // Authentication must remain available if audit storage is unavailable.
        }

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function completeOnboarding(CompleteOnboardingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->auditLogger->assertAvailable();
        $user = DB::transaction(function () use ($data, $request): User {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->getKey());
            abort_unless($user->must_change_password, 403);

            $user->forceFill([
                'password' => Hash::make($data['password']),
                'must_change_password' => false,
            ])->save();

            $this->auditAuth($request, $user);

            return $user;
        });

        return response()->json([
            'message' => 'Password updated.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function skipOnboarding(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->requiresOnboarding(), 403);

        $user->forceFill(['onboarding_skipped_at' => now()])->save();

        return response()->json([
            'message' => 'You can finish account setup later in Settings.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            try {
                $this->auditLogger->recordMutation(
                    AuditAction::Deleted,
                    AuditDomain::Accounts,
                    $user,
                    ['session'],
                    ['subject_label' => 'Session'],
                );
            } catch (Throwable) {
                // Logout must remain available if audit storage is unavailable.
            }
        }
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
     * PATCH /api/auth/profile — self-service profile update.
     * Sign-in email and role are intentionally immutable here.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->synchronizePersonName->forUpdate($user, $request->validated());
        $photoChanged = $request->exists('profile_photo');
        $newPhoto = null;
        $oldPhoto = $user->profilePhotoObject;
        if ($photoChanged) {
            if (is_string($data['profile_photo'] ?? null)) {
                preg_match('/^data:image\/(png|jpeg|webp);base64,(.*)$/sD', $data['profile_photo'], $matches);
                $bytes = isset($matches[2]) ? base64_decode($matches[2], true) : false;
                abort_if($bytes === false, 422, 'Profile photo is invalid.');
                $mime = 'image/'.($matches[1] === 'jpeg' ? 'jpeg' : $matches[1]);
                $newPhoto = $this->storedObjects->storeBytes($bytes, $mime, $matches[1], 'profile', 'profile.'.$matches[1]);
            }
            $data['profile_photo'] = null;
            $data['profile_photo_stored_object_id'] = $newPhoto?->id;
        }
        try {
            DB::transaction(function () use ($user, $data, $oldPhoto, $newPhoto, $photoChanged): void {
                $user->update($data);
                if ($photoChanged && $oldPhoto !== null && ! $oldPhoto->is($newPhoto)) {
                    DB::afterCommit(fn () => $this->storedObjects->deleteOrQueue($oldPhoto));
                }
            });
        } catch (Throwable $exception) {
            if ($newPhoto !== null) {
                $this->storedObjects->deleteOrQueue($newPhoto);
            }
            throw $exception;
        }

        return response()->json(new UserResource($user->fresh()));
    }

    public function profilePhoto(Request $request): StreamedResponse
    {
        $object = $request->user()->profilePhotoObject;
        abort_if($object === null, 404, 'Profile photo not found.');
        $stream = $this->storedObjects->readStream($object);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $object->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * POST /api/auth/password — self-service password change (rnd.md §9).
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($request, $user): void {
            $user->update([
                'password' => Hash::make($request->validated()['password']),
            ]);
            if ($user->must_change_password) {
                $user->completeOnboardingRequirement('must_change_password');
            }
            $user->tokens()->delete();
            $this->auditAuth($request, $user);
        });

        return response()->json(['message' => 'Password updated.']);
    }

    private function auditAuth(Request $request, ?User $user = null): AuditActivity
    {
        $subject = $user ?? $request->user();
        if (! $subject instanceof User) {
            throw new LogicException('Password changes require an authenticated user.');
        }

        return $this->auditLogger->recordMutation(
            AuditAction::Updated,
            AuditDomain::Accounts,
            $subject,
            ['password'],
            ['subject_label' => 'Password'],
            oldValues: [],
            newValues: [],
        );
    }

    private function auditFailedLogin(string $reason = 'Invalid credentials'): void
    {
        try {
            $this->auditLogger->record(
                AuditAction::Failed,
                AuditCategory::Security,
                AuditDomain::Accounts,
                details: [
                    'subject_label' => 'Login attempt',
                    'reason' => $reason,
                ],
            );
        } catch (Throwable) {
            // Authentication responses must survive an audit-store outage.
        }
    }
}
