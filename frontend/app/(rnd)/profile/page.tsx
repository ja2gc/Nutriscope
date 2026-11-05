"use client";

import { ProfilePageShell } from "@/components/profile/ProfilePageShell";

export default function ProfilePage() {
  return (
    <ProfilePageShell
      crumbs={[["Home", "/dashboard"], ["Profile"]]}
      fallbackRole="RND"
    />
  );
}
