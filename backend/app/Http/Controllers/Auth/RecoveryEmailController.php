<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateRecoveryEmailRequest;
use App\Http\Requests\Auth\VerifyRecoveryEmailRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\Auth\RecoveryEmailVerification;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class RecoveryEmailController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function update(UpdateRecoveryEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        $email = $request->validated('recovery_email');

        if ($user->must_set_recovery_email) {
            $this->auditLogger->assertAvailable();
            DB::transaction(function () use ($email, $user): void {
                $user->forceFill([
                    'recovery_email' => $email,
                    'recovery_email_verified_at' => now(),
                    'recovery_email_verification_code' => null,
                    'recovery_email_verification_expires_at' => null,
                    'pending_recovery_email' => null,
                ]);
                $user->completeOnboardingRequirement('must_set_recovery_email');
                $this->recordRecoveryEmailChange($user);
            });

            return response()->json([
                'message' => 'Recovery email saved.',
                'user' => new UserResource($user->fresh()),
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $hasVerifiedEmail = $user->recovery_email && $user->recovery_email_verified_at !== null;

        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($code, $email, $user, $hasVerifiedEmail): void {
            $user->forceFill([
                'recovery_email' => $hasVerifiedEmail ? $user->recovery_email : $email,
                'pending_recovery_email' => $hasVerifiedEmail ? $email : null,
                'recovery_email_verified_at' => $hasVerifiedEmail ? $user->recovery_email_verified_at : null,
                'recovery_email_verification_code' => Hash::make($code),
                'recovery_email_verification_expires_at' => now()->addMinutes(10),
            ])->save();
            $this->recordRecoveryEmailChange($user);
        });

        Notification::route('mail', $email)->notify(new RecoveryEmailVerification($code));

        return response()->json([
            'message' => 'Verification code sent.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function verify(VerifyRecoveryEmailRequest $request): JsonResponse
    {
        $this->auditLogger->assertAvailable();
        $user = DB::transaction(function () use ($request) {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->getKey());
            if (
                ! $user->recovery_email
                || ! $user->recovery_email_verification_code
                || ! $user->recovery_email_verification_expires_at
                || now()->greaterThan($user->recovery_email_verification_expires_at)
                || ! Hash::check($request->validated('code'), $user->recovery_email_verification_code)
            ) {
                return null;
            }

            $user->forceFill([
                'recovery_email' => $user->pending_recovery_email ?: $user->recovery_email,
                'pending_recovery_email' => null,
                'recovery_email_verified_at' => now(),
                'recovery_email_verification_code' => null,
                'recovery_email_verification_expires_at' => null,
            ])->save();

            $this->auditLogger->record(
                AuditAction::RecoveryEmailVerified,
                AuditCategory::Security,
                AuditDomain::Accounts,
                subject: $user,
                details: ['subject_public_id' => $user->uuid],
                actor: $user,
                includeRequestMetadata: false,
            );

            return $user;
        });

        if ($user === null) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        return response()->json([
            'message' => 'Recovery email verified.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    private function recordRecoveryEmailChange(User $user): void
    {
        $this->auditLogger->record(
            AuditAction::RecoveryEmailChanged,
            AuditCategory::Security,
            AuditDomain::Accounts,
            subject: $user,
            details: [
                'changed_fields' => ['recovery_email'],
                'subject_public_id' => $user->uuid,
            ],
            actor: $user,
            includeRequestMetadata: false,
        );
    }
}
