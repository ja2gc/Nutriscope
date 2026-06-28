"use client";

import { ProfilePageShell } from "@/components/profile/ProfilePageShell";

export default function AdminProfilePage() {
  return (
    <ProfilePageShell
      crumbs={[["Admin", "/admin/dashboard"], ["Profile"]]}
      fallbackRole="Admin"
      subtitle="Update your account details. Your name appears in audit logs and system records."
    />
  );
}
