"use client";

import React, { createContext, useContext, useState, useEffect, ReactNode } from "react";
import { User, loginUser, logoutUser, fetchCurrentUser } from "@/services/authService";

interface AuthContextType {
  user: User | null;
  loading: boolean;
  error: string | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const refreshUser = async () => {
    try {
      setLoading(true);
      setError(null);
      const currentUser = await fetchCurrentUser();
      setUser(currentUser);
    } catch (err: any) {
      setUser(null);
      // We don't want to set error for an unauthenticated user on initial load
      if (err.message && err.message !== "Failed to fetch user context." && !err.message.includes("Unauthenticated")) {
        setError(err.message);
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    refreshUser();
  }, []);

  const login = async (email: string, password: string) => {
    try {
      setLoading(true);
      setError(null);
      const res = await loginUser(email, password);
      setUser(res.user);
    } catch (err: any) {
      setError(err.message || "Invalid credentials.");
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
    } catch (err: any) {
      setError(err.message || "Failed to log out.");
      throw err;
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthContext.Provider value={{ user, loading, error, login, logout, refreshUser }}>
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
