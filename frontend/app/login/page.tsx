"use client";

import React, { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Logo } from "@/components/ui/Logo";
import { FssAppAccess } from "@/components/mobile-app/FssAppAccess";
import { AlertTriangle } from "lucide-react";

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
      } else if (user.role === "FSS") {
        router.replace("/mobile-app");
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
      <div className="relative hidden min-h-screen overflow-hidden p-12 text-white lg:flex lg:w-[55%] lg:flex-col lg:items-center lg:justify-center">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src="https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?auto=compress&cs=tinysrgb&w=1200"
          alt=""
          aria-hidden="true"
          className="absolute inset-0 h-full w-full object-cover"
        />
        <div className="absolute inset-0 bg-[linear-gradient(145deg,rgba(3,24,15,0.96)_0%,rgba(7,55,35,0.91)_55%,rgba(4,90,57,0.86)_100%)]" />
        <div className="absolute inset-0 bg-black/10" />

        <div aria-label="NutriScope" className="relative z-10 origin-center scale-[3.25]">
          <Logo variant="dark" />
        </div>
      </div>

      <div className="flex flex-1 items-center justify-center bg-white px-6 py-12">
        <div className="w-full max-w-[380px] space-y-7">
          <div className="mb-2 flex flex-col items-center text-center lg:hidden">
            <Logo variant="light" />
          </div>

          <div className="text-center">
            <h2 className="text-3xl font-extrabold tracking-tight text-warm-900">
              Welcome back
            </h2>
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

          <FssAppAccess compact />

        </div>
      </div>
    </div>
  );
}
