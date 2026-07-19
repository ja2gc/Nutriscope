"use client";

import React, { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Logo } from "@/components/ui/Logo";
import { AlertTriangle, HeartPulse, Lock, Salad, ShieldCheck } from "lucide-react";

const featureItems = [
  {
    icon: HeartPulse,
    text: "Run the full Nutrition Care Process",
  },
  {
    icon: Salad,
    text: "Plan menus and track budget down to the last PHP",
  },
  {
    icon: ShieldCheck,
    text: "Food service & kitchen operations",
  },
];

export default function LoginPage() {
  const router = useRouter();
  const { user, login, loading, error } = useAuth();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [validationError, setValidationError] = useState<string | null>(null);

  useEffect(() => {
    if (user) {
      if (user.onboarding_required && !user.onboarding_skipped) {
        router.replace("/account-setup");
        return;
      }
      if (user.role === "Admin") {
        router.replace("/admin/dashboard");
      } else {
        router.replace("/dashboard");
      }
    }
  }, [user, router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setValidationError(null);

    if (!email.trim() || !password.trim()) {
      setValidationError("Please enter both your email address and password.");
      return;
    }

    try {
      await login(email, password);
    } catch {
      // Error displayed via AuthContext
    }
  };

  return (
    <div className="flex min-h-screen w-full overflow-hidden bg-white font-sans">
      <div className="relative hidden min-h-screen overflow-hidden p-12 text-white lg:flex lg:w-[55%] lg:flex-col lg:justify-between">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src="https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?auto=compress&cs=tinysrgb&w=1200"
          alt=""
          aria-hidden="true"
          className="absolute inset-0 h-full w-full object-cover"
        />
        <div className="absolute inset-0 bg-[linear-gradient(155deg,rgba(4,28,16,0.92)_0%,rgba(8,51,34,0.82)_48%,rgba(5,150,105,0.66)_100%)]" />
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_78%_52%,rgba(255,255,255,0.18),transparent_24%),linear-gradient(90deg,rgba(0,0,0,0.26),transparent_58%)]" />

        <div className="relative z-10">
          <Logo variant="dark" collapsed={false} />
          <p className="mt-2 text-xs font-extrabold uppercase tracking-[0.16em] text-brand-green-200">
            Clinical & Operational Care Console
          </p>
        </div>

        <div className="relative z-10 max-w-lg space-y-6">
          <h1 className="text-5xl font-extrabold leading-[1.03] tracking-tight text-white drop-shadow-[0_3px_10px_rgba(0,0,0,0.55)]">
            Precision nutrition.
            <br />
            Operational clarity.
          </h1>
          <p className="max-w-md text-base font-medium leading-relaxed text-white/90 drop-shadow-[0_2px_6px_rgba(0,0,0,0.45)]">
            Run the full Nutrition Care Process and hospital food service operations from a single, unified platform.
          </p>

          <div className="flex flex-col gap-3 pt-2">
            {featureItems.map(({ icon: Icon, text }) => (
              <div key={text} className="flex max-w-md items-start gap-3">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-lime-300/18 text-lime-200 ring-1 ring-white/10">
                  <Icon className="h-4 w-4" />
                </span>
                <span className="pt-1.5 text-base font-semibold leading-snug text-white drop-shadow-[0_2px_5px_rgba(0,0,0,0.45)]">
                  {text}
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="relative z-10 flex items-center gap-2 text-sm font-medium text-white/65">
          <Lock className="h-3.5 w-3.5" />
          <span></span>
        </div>
      </div>

      <div className="flex flex-1 items-center justify-center bg-white px-6 py-12">
        <div className="w-full max-w-[380px] space-y-7">
          <div className="mb-2 flex flex-col items-center text-center lg:hidden">
            <Logo variant="light" />
            <p className="mt-2 text-xs font-bold uppercase tracking-widest text-warm-500">
              Clinical & Operational Care Console
            </p>
          </div>

          <div>
            <h2 className="text-3xl font-extrabold tracking-tight text-warm-900">
              Welcome back
            </h2>
            <p className="mt-2 text-base font-medium text-warm-500">
              Enter your credentials below to access your workspace.
            </p>
          </div>

          <form id="login-form" onSubmit={handleSubmit} className="space-y-4">
            {(error || validationError) && (
              <div id="login-error" className="rounded-lg border border-red-100 bg-red-50 p-3.5">
                <div className="flex gap-2.5">
                  <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                  <span className="text-sm font-semibold text-red-800">
                    {validationError || error}
                  </span>
                </div>
              </div>
            )}

            <Input
              label="Email Address"
              type="email"
              value={email}
              onChange={(e) => {
                setEmail(e.target.value);
                setValidationError(null);
              }}
              required
              disabled={loading}
              autoComplete="email"
              className="h-11"
            />

            <div>
              <Input
                label="Password"
                type="password"
                value={password}
                onChange={(e) => {
                  setPassword(e.target.value);
                  setValidationError(null);
                }}
                required
                disabled={loading}
                autoComplete="current-password"
                className="h-11"
              />

              <div className="mt-2 flex justify-end">
                <Link
                  href="/forgot-password"
                  className="text-sm font-semibold text-brand-green-700 hover:text-brand-green-800"
                >
                  Forgot password?
                </Link>
              </div>
            </div>

            <Button
              id="login-submit"
              type="submit"
              loading={loading}
              fullWidth
              className="h-11 shadow-[0_14px_28px_rgba(5,150,105,0.20)]"
            >
              Sign In
            </Button>
          </form>

          <div className="border-t border-warm-100 pt-5 text-center">
            <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-warm-400">
              RND - Admin - Food Service Staff
            </p>
          </div>

          <div className="text-center lg:hidden">
            <p className="select-none text-xs uppercase tracking-widest text-warm-400">
              Secure Connection - Activity Logs Active
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
