"use client";

import { ProfilePageShell } from "@/components/profile/ProfilePageShell";

export default function AdminProfilePage() {
  return (
    <ProfilePageShell
      crumbs={[["Admin", "/admin/dashboard"], ["Profile"]]}
      fallbackRole="Admin"
    />
  );
}
