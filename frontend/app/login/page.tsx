"use client";

import React, { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

export default function LoginPage() {
  const router = useRouter();
  const { user, login, loading, error } = useAuth();
  
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [validationError, setValidationError] = useState<string | null>(null);

  // If user is already logged in, redirect them to dashboard
  useEffect(() => {
    if (user) {
      router.replace("/dashboard");
    }
  }, [user, router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setValidationError(null);

    // Basic validation
    if (!email.trim() || !password.trim()) {
      setValidationError("Please fill in all fields.");
      return;
    }

    try {
      await login(email, password);
      router.replace("/dashboard");
    } catch (err) {
      // Error is already set and displayed via AuthContext, we just catch to avoid unhandled rejection
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 sm:px-6 lg:px-8 font-sans">
      <div className="w-full max-w-md space-y-6">
        {/* Brand Header */}
        <div className="text-center">
          <h1 className="text-2xl font-extrabold tracking-wider text-blue-900 uppercase">
            NutriScope
          </h1>
          <p className="mt-1.5 text-xs font-semibold text-gray-500 uppercase tracking-widest">
            Clinical & Operational Care System
          </p>
        </div>

        {/* Login Card */}
        <div className="bg-white px-8 py-10 border border-gray-200 rounded shadow-sm">
          <div className="mb-6 border-b border-gray-150 pb-4">
            <h2 className="text-lg font-bold text-gray-900">
              System Authentication
            </h2>
            <p className="mt-1 text-xs text-gray-500">
              Authorized clinical personnel access only. Actions are audited.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Display Error Message */}
            {(error || validationError) && (
              <div className="p-3 bg-red-50 border border-red-200 rounded">
                <div className="flex gap-2">
                  <svg 
                    className="h-4 w-4 text-red-600 shrink-0 mt-0.5" 
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
              placeholder="e.g., rnd@nutriscope.com"
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
              <Button type="submit" loading={loading}>
                Sign In to System
              </Button>
            </div>
          </form>
        </div>

        {/* Footer Audit Notice */}
        <div className="text-center">
          <p className="text-[10px] text-gray-400 select-none uppercase tracking-widest">
            Protected by AuditMiddleware • Session Log Active
          </p>
        </div>
      </div>
    </div>
  );
}
