"use client";

import React, { createContext, useContext, useState, useEffect, useCallback, ReactNode } from "react";
import { useRouter, usePathname } from "next/navigation";
import { User, loginUser, logoutUser, fetchCurrentUser } from "@/services/authService";

interface AuthContextType {
  user: User | null;
  initializing: boolean;
  loading: boolean;
  error: string | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [initializing, setInitializing] = useState<boolean>(true);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();
  const pathname = usePathname();

  const refreshUser = useCallback(async () => {
    try {
      setError(null);
      const currentUser = await fetchCurrentUser();
      setUser(currentUser);
    } catch (err: unknown) {
      setUser(null);
      const message = err instanceof Error ? err.message : "";
      if (message && message !== "Failed to fetch user context." && !message.includes("Unauthenticated")) {
        setError(message);
      }
    } finally {
      setInitializing(false);
    }
  }, []);

  useEffect(() => {
    refreshUser();
  }, [refreshUser]);

  // Handle bfcache restore (browser back/forward from frozen page state)
  useEffect(() => {
    const handlePageShow = (event: PageTransitionEvent) => {
      if (event.persisted) {
        refreshUser();
      }
    };
    window.addEventListener("pageshow", handlePageShow);
    return () => window.removeEventListener("pageshow", handlePageShow);
  }, [refreshUser]);

  // Redirect to login whenever auth check completes with no user
  useEffect(() => {
    if (!initializing && !user && pathname !== "/login") {
      router.replace("/login");
    }
  }, [initializing, user, pathname, router]);

  const login = async (email: string, password: string) => {
    try {
      setLoading(true);
      setError(null);
      const res = await loginUser(email, password);
      setUser(res.user);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Invalid credentials.";
      setError(message);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    try {
      setLoading(true);
      setError(null);
      await logoutUser();
      setUser(null);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to log out.";
      setError(message);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthContext.Provider value={{ user, initializing, loading, error, login, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
