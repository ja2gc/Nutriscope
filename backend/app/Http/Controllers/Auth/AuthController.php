<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\SynchronizePersonName;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteOnboardingRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditActivity;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SynchronizePersonName $synchronizePersonName,
    ) {}

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            $this->auditLogin($request, AuditAction::LoginFailed);

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = $request->user() ?? User::where('email', $request->email)->first();

        if (! $user->is_active) {
            $this->auditLogin($request, AuditAction::LoginFailed, $user);
            Auth::logout();

            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        // Platform-based role gating: the FSS mobile app is for Food Service staff
        // only; the web console is for RND and Admin only.
        $isApp = $request->validated('platform') === 'app';

        if ($isApp && ! $user->isFss()) {
            $this->auditLogin($request, AuditAction::LoginFailed, $user, ['platform' => 'app']);
            Auth::logout();

            return response()->json(['message' => 'This app is for Food Service staff only.'], 403);
        }

        if (! $isApp && $user->isFss()) {
            $this->auditLogin($request, AuditAction::LoginFailed, $user, ['platform' => 'web']);
            Auth::logout();

            return response()->json(['message' => 'Food Service staff must sign in through the mobile app.'], 403);
        }

        // Revoke only tokens for this device, then issue a fresh one
        $tokenName = $request->validated()['device_name'] ?? 'nutriscope-token';
        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName, [$user->role])->plainTextToken;

        $this->auditLogin($request, AuditAction::LoginSucceeded, $user, [
            'platform' => $request->validated('platform') ?? 'web',
        ]);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function completeOnboarding(CompleteOnboardingRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($data, $request, $user): void {
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'recovery_email' => strtolower($data['recovery_email']),
                'recovery_email_verified_at' => now(),
                'recovery_email_verification_code' => null,
                'recovery_email_verification_expires_at' => null,
                'pending_recovery_email' => null,
                'must_change_password' => false,
                'must_set_recovery_email' => false,
                'onboarding_skipped_at' => null,
            ])->save();

            $this->auditAuth($request, AuditAction::PasswordChanged, $user);
            $this->auditLogger->record(
                AuditAction::RecoveryEmailChanged,
                AuditCategory::Security,
                AuditDomain::Accounts,
                subject: $user,
                details: ['changed_fields' => ['recovery_email']],
                actor: $user,
                includeRequestMetadata: false,
            );
        });

        return response()->json([
            'message' => 'Account setup complete.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function skipOnboarding(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->requiresOnboarding(), 403);

        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($user): void {
            $user->forceFill(['onboarding_skipped_at' => now()])->save();
            $this->auditLogger->record(
                AuditAction::SettingsChanged,
                AuditCategory::Security,
                AuditDomain::Accounts,
                subject: $user,
                details: ['changed_fields' => ['onboarding_skipped_at']],
                actor: $user,
                includeRequestMetadata: false,
            );
        });

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
        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($request, $user): void {
            $request->user()->currentAccessToken()->delete();
            $this->auditAuth($request, AuditAction::Logout, $user);
        });

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
        $data = $this->synchronizePersonName->forUpdate($user, $request->validated());
        $oldNameValues = $this->accountNameAuditValues($user);
        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($user, $data, $oldNameValues): void {
            $user->update($data);
            $newNameValues = $this->accountNameAuditValues($user);
            $changedNameFields = collect(array_keys($oldNameValues))
                ->filter(fn (string $field): bool => $oldNameValues[$field] !== $newNameValues[$field])
                ->values()
                ->all();
            $this->auditLogger->record(
                AuditAction::ProfileChanged,
                AuditCategory::Security,
                AuditDomain::Accounts,
                subject: $user,
                details: ['changed_fields' => array_keys($data)],
                actor: $user,
                includeRequestMetadata: false,
                oldValues: array_intersect_key($oldNameValues, array_flip($changedNameFields)),
                newValues: array_intersect_key($newNameValues, array_flip($changedNameFields)),
            );
        });

        return response()->json(new UserResource($user->fresh()));
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
            $this->auditAuth($request, AuditAction::PasswordChanged, $user);
        });

        return response()->json(['message' => 'Password updated.']);
    }

    private function auditAuth(
        Request $request,
        AuditAction $action,
        ?User $user = null,
        array $properties = [],
    ): AuditActivity {
        $details = $properties;

        return $this->auditLogger->record(
            $action,
            AuditCategory::Security,
            AuditDomain::Accounts,
            outcome: $action === AuditAction::LoginFailed ? AuditOutcome::Failure : AuditOutcome::Success,
            severity: $action === AuditAction::LoginFailed ? AuditSeverity::Warning : AuditSeverity::Info,
            details: $details,
            actor: $user,
            includeRequestMetadata: false,
        );
    }

    private function auditLogin(
        Request $request,
        AuditAction $action,
        ?User $user = null,
        array $properties = [],
    ): void {
        try {
            $this->auditAuth($request, $action, $user, $properties);
        } catch (Throwable $exception) {
            try {
                Log::warning('Login audit telemetry failed.', [
                    'exception_class' => $exception::class,
                    'audit_action' => $action->value,
                ]);
            } catch (Throwable) {
            }
        }
    }

    /** @return array{name: string, first_name: ?string, last_name: ?string} */
    private function accountNameAuditValues(User $user): array
    {
        return [
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];
    }
}
