"use client";

import React, { Suspense, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { KeyRound } from "lucide-react";
import { Logo } from "@/components/ui/Logo";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { resetPassword } from "@/services/authService";

function ResetPasswordForm() {
  const params = useSearchParams();
  const [email, setEmail] = useState(params.get("email") ?? "");
  const [token, setToken] = useState(params.get("token") ?? "");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setMessage(null);
    setError(null);
    setLoading(true);
    try {
      setMessage(await resetPassword({
        email,
        token,
        password,
        password_confirmation: passwordConfirmation,
      }));
      setPassword("");
      setPasswordConfirmation("");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to reset password.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-zinc-50 px-4 sm:px-6 lg:px-8 font-sans">
      <div className="w-full max-w-md space-y-6">
        <div className="flex flex-col items-center text-center">
          <Logo variant="light" />
          <p className="mt-2 text-xs font-semibold text-zinc-500 uppercase tracking-widest">
            Account Recovery
          </p>
        </div>

        <div className="bg-white px-8 py-10 border border-zinc-200 rounded-2xl shadow-sm">
          <div className="mb-6 border-b border-zinc-100 pb-4">
            <h1 className="text-lg font-bold text-zinc-900 flex items-center gap-2">
              <KeyRound className="h-4 w-4 text-emerald-600" />
              Set New Password
            </h1>
            <p className="mt-1 text-xs text-zinc-500">
              Use the token from your reset link to set a new password.
            </p>
          </div>

          <form onSubmit={submit} className="space-y-4">
            {message && (
              <div className="rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-xs font-semibold text-emerald-700">
                {message}
              </div>
            )}
            {error && (
              <div className="rounded-lg border border-red-100 bg-red-50 p-3 text-xs font-semibold text-red-700">
                {error}
              </div>
            )}
            <Input label="Email Address" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" />
            <Input label="Reset Token" value={token} onChange={(e) => setToken(e.target.value)} required />
            <Input label="New Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} autoComplete="new-password" />
            <Input label="Confirm New Password" type="password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} required minLength={8} autoComplete="new-password" />
            <Button type="submit" loading={loading} fullWidth>
              Reset Password
            </Button>
          </form>

          <div className="mt-5 text-center">
            <Link href="/login" className="text-xs font-semibold text-zinc-500 hover:text-zinc-800">
              Back to sign in
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={null}>
      <ResetPasswordForm />
    </Suspense>
  );
}
