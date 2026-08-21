"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AlertTriangle, Lock } from "lucide-react";
import { useAuth } from "@/contexts/AuthContext";
import { loginUser } from "@/services/authService";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Logo } from "@/components/ui/Logo";

export function FssLogin() {
  const router = useRouter();
  const { user, refreshUser } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!user) return;
    if (user.role !== "FSS") {
      router.replace(user.role === "Admin" ? "/admin/dashboard" : "/dashboard");
      return;
    }
    router.replace(user.onboarding_required && !user.onboarding_skipped ? "/fss/account-setup" : "/fss");
  }, [router, user]);

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const result = await loginUser(email, password, "app");
      if (result.user.role !== "FSS") throw new Error("This app is only for Food Service Staff.");
      await refreshUser();
      router.replace(result.user.onboarding_required && !result.user.onboarding_skipped ? "/fss/account-setup" : "/fss");
    } catch (caught: unknown) {
      setError(caught instanceof Error ? caught.message : "Sign in failed.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="flex min-h-dvh items-center justify-center bg-warm-50 p-5" style={{ paddingTop: "max(env(safe-area-inset-top), 1.25rem)", paddingBottom: "max(env(safe-area-inset-bottom), 1.25rem)" }}>
      <section className="w-full max-w-md rounded-3xl border border-warm-200 bg-white p-6 shadow-sm sm:p-8">
        <Logo variant="light" />
        <p className="mt-6 text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700">Food Service Staff</p>
        <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-warm-900">Sign in to NutriScope</h1>
        <p className="mt-2 text-sm leading-6 text-warm-500">Mobile access for kitchen and food service work.</p>

        <form onSubmit={submit} className="mt-6 space-y-4">
          {error && (
            <p role="alert" className="flex gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />{error}
            </p>
          )}
          <Input label="Email address" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required disabled={loading} autoComplete="email" className="h-12" />
          <Input label="Password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} required disabled={loading} autoComplete="current-password" className="h-12" />
          <Button type="submit" loading={loading} fullWidth className="min-h-12">Sign in</Button>
        </form>

        <div className="mt-5 flex items-center justify-between gap-3 text-sm">
          <Link href="/forgot-password" target="_blank" className="font-semibold text-emerald-700 hover:text-emerald-800">Forgot password?</Link>
          <span className="inline-flex items-center gap-1 text-xs text-warm-400"><Lock className="h-3.5 w-3.5" aria-hidden="true" />Secure access</span>
        </div>
      </section>
    </main>
  );
}
