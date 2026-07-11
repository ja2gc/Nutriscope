<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const MESSAGE = 'If that email exists, a password reset link has been sent.';

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function sendResetLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower($data['email']);
        $user = User::where('recovery_email', $email)
            ->whereNotNull('recovery_email_verified_at')
            ->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);
        }

        return response()->json(['message' => self::MESSAGE]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $this->auditLogger->assertAvailable();
        $user = DB::transaction(function () use ($data) {
            $user = User::query()->where('recovery_email', Str::lower($data['email']))
                ->whereNotNull('recovery_email_verified_at')->lockForUpdate()->first();
            if (! $user) {
                return null;
            }
            DB::table(config('auth.passwords.users.table'))->where('email', $user->getEmailForPasswordReset())->lockForUpdate()->first();
            if (! Password::broker()->tokenExists($user, $data['token'])) {
                return null;
            }
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
            ])->save();

            Password::broker()->deleteToken($user);
            $user->tokens()->delete();

            $this->auditLogger->record(
                AuditAction::PasswordReset,
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
            return response()->json(['message' => 'Invalid or expired password reset token.'], 422);
        }

        event(new PasswordReset($user));

        return response()->json(['message' => 'Password reset.']);
    }
}
