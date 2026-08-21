"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ShieldCheck } from "lucide-react";
import { useAuth } from "@/contexts/AuthContext";
import { completeOnboarding, skipOnboarding } from "@/services/authService";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

export function AccountSetup({ appMode = false }: { appMode?: boolean }) {
  const router = useRouter();
  const { user, initializing, refreshUser } = useAuth();
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [recoveryEmail, setRecoveryEmail] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const destination = appMode ? "/fss" : user?.role === "Admin" ? "/admin/dashboard" : user?.role === "FSS" ? "/fss" : "/dashboard";
  const loginPath = appMode ? "/fss/login" : "/login";

  useEffect(() => {
    if (initializing) return;
    if (!user) router.replace(loginPath);
    else if (appMode && user.role !== "FSS") router.replace(user.role === "Admin" ? "/admin/dashboard" : "/dashboard");
    else if (!user.onboarding_required) router.replace(destination);
  }, [appMode, destination, initializing, loginPath, router, user]);

  async function finish(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    try {
      await completeOnboarding({ password, password_confirmation: confirmation, recovery_email: recoveryEmail });
      await refreshUser();
      router.replace(destination);
    } catch (caught: unknown) {
      setError(caught instanceof Error ? caught.message : "Account setup failed.");
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

  if (initializing || !user || (appMode && user.role !== "FSS")) return <main className="min-h-dvh animate-pulse bg-warm-50" aria-busy="true" />;

  return (
    <main className="flex min-h-dvh items-center justify-center bg-warm-50 p-5 sm:p-8">
      <form onSubmit={finish} className="w-full max-w-lg space-y-5 rounded-3xl border border-warm-200 bg-white p-6 shadow-sm sm:p-8">
        <div className="flex items-start gap-3 border-b border-warm-100 pb-5">
          <span className="rounded-xl border border-emerald-100 bg-emerald-50 p-2.5 text-emerald-700"><ShieldCheck aria-hidden="true" className="h-5 w-5" /></span>
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">First login</p>
            <h1 className="mt-1 text-2xl font-extrabold text-warm-900">Secure your account</h1>
            <p className="mt-2 text-sm leading-6 text-warm-500">Replace the temporary password and add a recovery email. No email verification code is needed now.</p>
          </div>
        </div>
        <Input label="New password" type="password" minLength={8} required value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="new-password" />
        <Input label="Confirm new password" type="password" minLength={8} required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} autoComplete="new-password" />
        <Input label="Recovery email" type="email" required value={recoveryEmail} onChange={(event) => setRecoveryEmail(event.target.value)} autoComplete="email" />
        {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>}
        <div className="grid gap-2 sm:grid-cols-2">
          <Button type="submit" loading={saving} fullWidth className="min-h-11">Save account setup</Button>
          <Button type="button" variant="secondary" onClick={() => void doLater()} disabled={saving} fullWidth className="min-h-11">Do later</Button>
        </div>
        <p className="text-xs leading-5 text-warm-500">If deferred, this reminder stays visible until both items are completed in Profile settings.</p>
      </form>
    </main>
  );
}
