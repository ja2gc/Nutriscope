<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateRecoveryEmailRequest;
use App\Http\Requests\Auth\VerifyRecoveryEmailRequest;
use App\Http\Resources\UserResource;
use App\Notifications\Auth\RecoveryEmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class RecoveryEmailController extends Controller
{
    public function update(UpdateRecoveryEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        $email = $request->validated('recovery_email');
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'recovery_email' => $email,
            'recovery_email_verified_at' => null,
            'recovery_email_verification_code' => Hash::make($code),
            'recovery_email_verification_expires_at' => now()->addMinutes(10),
        ])->save();

        Notification::route('mail', $email)->notify(new RecoveryEmailVerification($code));

        return response()->json([
            'message' => 'Verification code sent.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function verify(VerifyRecoveryEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        if (
            ! $user->recovery_email
            || ! $user->recovery_email_verification_code
            || ! $user->recovery_email_verification_expires_at
            || now()->greaterThan($user->recovery_email_verification_expires_at)
            || ! Hash::check($request->validated('code'), $user->recovery_email_verification_code)
        ) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        $user->forceFill([
            'recovery_email_verified_at' => now(),
            'recovery_email_verification_code' => null,
            'recovery_email_verification_expires_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Recovery email verified.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}
