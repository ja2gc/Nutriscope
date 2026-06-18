"use client";

import React, { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Logo } from "@/components/ui/Logo";

export default function LoginPage() {
  const router = useRouter();
  const { user, login, loading, error } = useAuth();
  
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [validationError, setValidationError] = useState<string | null>(null);

  // If user is already logged in, redirect them to appropriate dashboard
  useEffect(() => {
    if (user) {
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

    // Basic validation
    if (!email.trim() || !password.trim()) {
      setValidationError("Please enter both your email address and password.");
      return;
    }

    try {
      await login(email, password);
    } catch (err) {
      // Error is already set and displayed via AuthContext
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-zinc-50 px-4 sm:px-6 lg:px-8 font-sans">
      <div className="w-full max-w-md space-y-6">
        {/* Brand Header */}
        <div className="flex flex-col items-center text-center">
          <Logo variant="light" />
          <p className="mt-2 text-xs font-semibold text-zinc-500 uppercase tracking-widest">
            Clinical & Operational Care Console
          </p>
        </div>

        {/* Login Card */}
        <div className="bg-white px-8 py-10 border border-zinc-200 rounded-2xl shadow-sm">
          <div className="mb-6 border-b border-zinc-100 pb-4">
            <h2 className="text-lg font-bold text-zinc-900">
              Sign In
            </h2>
            <p className="mt-1 text-xs text-zinc-500">
              Enter your credentials below to access your workspace.
            </p>
          </div>

          <form id="login-form" onSubmit={handleSubmit} className="space-y-4">
            {/* Display Error Message */}
            {(error || validationError) && (
              <div id="login-error" className="p-3.5 bg-red-50 border border-red-100 rounded-lg">
                <div className="flex gap-2.5">
                  <svg 
                    className="h-4.5 w-4.5 text-red-600 shrink-0 mt-0.5" 
                    fill="none" 
                    viewBox="0 0 24 24" 
                    stroke="currentColor"
                  >
                    <path 
                      strokeLinecap="round" 
                      strokeLinejoin="round" 
                      strokeWidth="2" 
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" 
                    />
                  </svg>
                  <span className="text-xs font-semibold text-red-800">
                    {validationError || error}
                  </span>
                </div>
              </div>
            )}

            <Input
              label="Email Address"
              type="email"
              placeholder="e.g., rnd@nutriscope.local"
              value={email}
              onChange={(e) => {
                setEmail(e.target.value);
                setValidationError(null);
              }}
              required
              disabled={loading}
              autoComplete="email"
            />

            <Input
              label="Password"
              type="password"
              placeholder="••••••••"
              value={password}
              onChange={(e) => {
                setPassword(e.target.value);
                setValidationError(null);
              }}
              required
              disabled={loading}
              autoComplete="current-password"
            />

            <div className="pt-2">
              <Button id="login-submit" type="submit" loading={loading}>
                Sign In to Console
              </Button>
            </div>
          </form>
        </div>

        {/* Footer Audit Notice */}
        <div className="text-center">
          <p className="text-[10px] text-zinc-400 select-none uppercase tracking-widest">
            Secure Connection • Activity Logs Active
          </p>
        </div>
      </div>
    </div>
  );
}

