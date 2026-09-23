"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import {
  completeOnboarding,
  skipOnboarding,
  updateRecoveryEmail,
  verifyRecoveryEmail,
} from "@/services/authService";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

export function AccountSetup() {
  const router = useRouter();
  const { user, initializing, refreshUser } = useAuth();
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [recoveryEmail, setRecoveryEmail] = useState("");
  const [verificationCode, setVerificationCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const destination = user?.role === "Admin" ? "/admin/dashboard" : user?.role === "FSS" ? "/mobile-app" : "/dashboard";
  const verificationEmail = user?.pending_recovery_email ?? user?.recovery_email;
  const passwordStage = Boolean(user?.must_change_password);
  const verificationStage = Boolean(user && !user.must_change_password && user.must_set_recovery_email && verificationEmail);
  const recoveryEmailStage = Boolean(user && !user.must_change_password && user.must_set_recovery_email && !verificationEmail);

  useEffect(() => {
    if (initializing) return;
    if (!user) router.replace("/login");
    else if (!user.onboarding_required) router.replace(destination);
  }, [destination, initializing, router, user]);

  useEffect(() => {
    if (verificationEmail) setRecoveryEmail((current) => current || verificationEmail);
  }, [verificationEmail]);

  async function submitPassword(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      await completeOnboarding({ password, password_confirmation: confirmation });
      await refreshUser();
      setPassword("");
      setConfirmation("");
    } catch (caught: unknown) {
      setError(caught instanceof Error ? caught.message : "Password could not be updated.");
    } finally {
      setSaving(false);
    }
  }

  async function sendCode(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const result = await updateRecoveryEmail(recoveryEmail);
      await refreshUser();
      setNotice(result.message);
    } catch (caught: unknown) {
      setError(caught instanceof Error ? caught.message : "Verification code could not be sent. Check the address and try again.");
    } finally {
      setSaving(false);
    }
  }

  async function verifyCode(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const result = await verifyRecoveryEmail(verificationCode);
      await refreshUser();
      if (!result.user.onboarding_required) router.replace(destination);
    } catch (caught: unknown) {
      setError(caught instanceof Error ? caught.message : "Verification failed.");
    } finally {
      setSaving(false);
    }
  }

  async function resendCode() {
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const result = await updateRecoveryEmail(recoveryEmail);
      await refreshUser();
      setNotice(result.message);
    } catch (caught: unknown) {
      setError(caught instanceof Error ? caught.message : "Verification code could not be sent. Check the address and try again.");
    } finally {
      setSaving(false);
    }
  }

  async function doLater() {
    setSaving(true);
    setError(null);
    try {
      await skipOnboarding();
      await refreshUser();
      router.replace(destination);
    } catch (caught: unknown) {
      setError(caught instanceof Error ? caught.message : "Setup could not be deferred.");
    } finally {
      setSaving(false);
    }
  }

  if (initializing || !user) return <main className="min-h-dvh animate-pulse bg-warm-50" aria-busy="true" />;

  return (
    <main className="flex min-h-dvh items-center justify-center bg-warm-50 p-5 sm:p-8">
      <section className="w-full max-w-lg space-y-5 rounded-3xl border border-warm-200 bg-white p-6 shadow-sm sm:p-8">
        <div className="border-b border-warm-100 pb-5">
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">First login</p>
          <h1 className="mt-1 text-2xl font-extrabold text-warm-900">Secure your account</h1>
          <p className="mt-2 text-sm leading-6 text-warm-500">
            {passwordStage
              ? "Replace your temporary password."
              : recoveryEmailStage
                ? "Add a recovery email for password reset and account recovery."
                : "Enter the six-digit code sent to your recovery email."}
          </p>
        </div>

        {passwordStage ? (
          <form onSubmit={submitPassword} className="space-y-5">
            <Input label="New password" type="password" minLength={8} required value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="new-password" />
            <Input label="Confirm new password" type="password" minLength={8} required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} autoComplete="new-password" />
            {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>}
            <div className="grid gap-2 sm:grid-cols-2">
              <Button type="submit" loading={saving} fullWidth className="min-h-11">Next</Button>
              <Button type="button" variant="secondary" onClick={() => void doLater()} disabled={saving} fullWidth className="min-h-11">Do later</Button>
            </div>
          </form>
        ) : recoveryEmailStage ? (
          <form onSubmit={sendCode} className="space-y-5">
            <Input label="Recovery email" type="email" required value={recoveryEmail} onChange={(event) => setRecoveryEmail(event.target.value)} autoComplete="email" />
            {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>}
            <div className="grid gap-2 sm:grid-cols-2">
              <Button type="submit" loading={saving} fullWidth className="min-h-11">Send verification code</Button>
              <Button type="button" variant="secondary" onClick={() => void doLater()} disabled={saving} fullWidth className="min-h-11">Do later</Button>
            </div>
          </form>
        ) : verificationStage ? (
          <form onSubmit={verifyCode} className="space-y-5">
            <Input label="Verification code" required inputMode="numeric" maxLength={6} value={verificationCode} onChange={(event) => setVerificationCode(event.target.value)} autoComplete="one-time-code" />
            {notice && <p className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{notice}</p>}
            {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>}
            <div className="grid gap-2 sm:grid-cols-2">
              <Button type="submit" loading={saving} fullWidth className="min-h-11">Verify recovery email</Button>
              <Button type="button" variant="secondary" onClick={() => void resendCode()} disabled={saving} fullWidth className="min-h-11">Send another code</Button>
              <Button type="button" variant="secondary" onClick={() => void doLater()} disabled={saving} fullWidth className="min-h-11 sm:col-span-2">Do later</Button>
            </div>
          </form>
        ) : null}
      </section>
    </main>
  );
}
