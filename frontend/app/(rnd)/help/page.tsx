"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { HelpPage } from "@/components/help/HelpPage";
import { useAuth } from "@/contexts/AuthContext";

export default function RndHelpPage() {
  const { user, initializing } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (initializing || !user) return;
    if (user.role === "Admin") router.replace("/admin/help");
    else if (user.role !== "RND") router.replace("/login");
  }, [initializing, router, user]);

  if (initializing || user?.role !== "RND") return null;

  return <HelpPage role="RND" />;
}
