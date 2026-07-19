"use client";

import Link from "next/link";
import { AlertTriangle } from "lucide-react";
import { useAuth } from "@/contexts/AuthContext";

export function OnboardingReminder() {
  const { user } = useAuth();

  if (!user?.onboarding_required || !user.onboarding_skipped) return null;

  const href = user.role === "Admin" ? "/admin/profile" : "/profile";

  return (
    <aside className="sticky top-0 z-40 border-b border-amber-200 bg-amber-50" aria-label="Account setup reminder">
      <div className="mx-auto flex min-h-11 max-w-screen-2xl items-center gap-3 px-4 py-2 text-sm text-amber-950">
        <AlertTriangle aria-hidden="true" className="h-4 w-4 shrink-0 text-amber-700" />
        <p className="flex-1 font-medium">Finish your password and recovery email setup in Profile settings.</p>
        <Link
          className="inline-flex min-h-11 items-center rounded-lg px-3 font-bold text-amber-900 underline-offset-4 hover:bg-amber-100 hover:underline focus:outline-none focus:ring-2 focus:ring-amber-600/40"
          href={href}
        >
          Open settings
        </Link>
      </div>
    </aside>
  );
}
