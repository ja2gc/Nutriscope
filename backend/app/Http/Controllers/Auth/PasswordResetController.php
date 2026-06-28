<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const MESSAGE = 'If that email exists, a password reset link has been sent.';

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

        $user = User::where('recovery_email', Str::lower($data['email']))
            ->whereNotNull('recovery_email_verified_at')
            ->first();

        if (! $user || ! Password::broker()->tokenExists($user, $data['token'])) {
            return response()->json(['message' => 'Invalid or expired password reset token.'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        Password::broker()->deleteToken($user);
        $user->tokens()->delete();

        activity('audit')
            ->causedBy($user)
            ->event('password_reset')
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ])
            ->log('Password reset');

        event(new PasswordReset($user));

        return response()->json(['message' => 'Password reset.']);
    }
}
