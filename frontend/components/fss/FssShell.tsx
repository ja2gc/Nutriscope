"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { ClipboardCheck, CookingPot, Home, LogOut, MenuSquare, ShoppingBag } from "lucide-react";
import { useEffect, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { isPhoneOrTablet } from "@/lib/pwa";
import { Logo } from "@/components/ui/Logo";
import { FssDesktopHandoff } from "./FssDesktopHandoff";

const items = [
  { label: "Home", href: "/fss", icon: Home },
  { label: "Menu", href: "/fss/menu", icon: MenuSquare },
  { label: "Meal Prep", href: "/fss/meal-prep", icon: CookingPot },
  { label: "Accomplish", href: "/fss/accomplish", icon: ClipboardCheck },
  { label: "Purchase", href: "/fss/purchase", icon: ShoppingBag },
];

export function FssShell({ children }: { children: React.ReactNode }) {
  const { user, initializing, logout } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const [mobileDevice, setMobileDevice] = useState<boolean | null>(null);

  useEffect(() => {
    const update = () => setMobileDevice(isPhoneOrTablet({
      coarsePointer: window.matchMedia("(any-pointer: coarse)").matches,
      viewportWidth: window.innerWidth,
    }));
    update();
    window.addEventListener("resize", update);
    return () => window.removeEventListener("resize", update);
  }, []);

  useEffect(() => {
    if (initializing) return;
    if (!user) router.replace("/login");
    else if (user.role !== "FSS") router.replace(user.role === "Admin" ? "/admin/dashboard" : "/dashboard");
  }, [initializing, router, user]);

  if (initializing || mobileDevice === null) {
    return <div className="grid min-h-dvh place-items-center bg-warm-50 text-sm font-bold text-warm-500">Loading NutriScope…</div>;
  }
  if (!user || user.role !== "FSS") return null;
  if (!mobileDevice) return <FssDesktopHandoff />;

  async function signOut() {
    await logout();
    router.replace("/login");
  }

  return (
    <div className="min-h-dvh bg-warm-50 text-warm-900">
      <header className="sticky top-0 z-30 border-b border-warm-200 bg-white/95 px-4 backdrop-blur" style={{ paddingTop: "env(safe-area-inset-top)" }}>
        <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3">
          <Logo variant="light" collapsed />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-extrabold">Food Service Staff</p>
            <p className="truncate text-xs text-warm-500">{user.display_name}</p>
          </div>
          <button
            type="button"
            onClick={() => void signOut()}
            className="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-xl text-warm-500 hover:bg-warm-100 hover:text-warm-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
            aria-label="Sign out"
          >
            <LogOut className="h-5 w-5" aria-hidden="true" />
          </button>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl overflow-x-hidden p-4 pb-28 sm:p-6 sm:pb-28">{children}</main>

      <nav
        className="fixed inset-x-0 bottom-0 z-40 border-t border-warm-200 bg-white"
        style={{ paddingBottom: "max(env(safe-area-inset-bottom), 0px)" }}
        aria-label="Food Service Staff navigation"
      >
        <div className="mx-auto flex h-16 max-w-3xl items-stretch">
          {items.map(({ label, href, icon: Icon }) => {
            const active = href === "/fss" ? pathname === href : pathname.startsWith(href);
            return (
              <Link
                key={href}
                href={href}
                aria-current={active ? "page" : undefined}
                className={`relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 px-1 text-center transition-colors ${active ? "text-emerald-700" : "text-warm-400 hover:text-warm-700"}`}
              >
                <span className={`absolute inset-x-3 top-0 h-0.5 rounded-full ${active ? "bg-emerald-600" : "bg-transparent"}`} />
                <Icon className="h-5 w-5" aria-hidden="true" />
                <span className="max-w-full truncate text-[11px] font-bold leading-none sm:text-xs">{label}</span>
              </Link>
            );
          })}
        </div>
      </nav>
    </div>
  );
}
