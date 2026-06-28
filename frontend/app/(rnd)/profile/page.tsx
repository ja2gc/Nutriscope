"use client";

import { ProfilePageShell } from "@/components/profile/ProfilePageShell";

export default function ProfilePage() {
  return (
    <ProfilePageShell
      crumbs={[["Home", "/dashboard"], ["Profile"]]}
      fallbackRole="RND"
      subtitle="Update your account details. Your name is what appears as Prepared by on reports you file."
    />
  );
}
