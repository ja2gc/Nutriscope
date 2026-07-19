export interface User {
  id: number;
  first_name: string | null;
  last_name: string | null;
  display_name: string;
  name: string;
  email: string;
  recovery_email?: string | null;
  recovery_email_verified?: boolean;
  contact_number?: string | null;
  profile_photo?: string | null;
  role: "RND" | "FSS" | "Admin";
  is_active: boolean;
  onboarding_required?: boolean;
  onboarding_skipped?: boolean;
  must_change_password?: boolean;
  must_set_recovery_email?: boolean;
  pending_recovery_email?: string | null;
  created_at: string;
}

export interface LoginResponse {
  user: User;
}

export async function loginUser(email: string, password: string): Promise<LoginResponse> {
  const res = await fetch("/api/auth/login", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ email, password }),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to log in.");
  }

  return res.json();
}

export async function logoutUser(): Promise<void> {
  const res = await fetch("/api/auth/logout", {
    method: "POST",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to log out.");
  }
}

export async function updateProfile(data: {
  first_name?: string;
  last_name?: string;
  name?: string;
  email: string;
  contact_number?: string | null;
  profile_photo?: string | null;
}): Promise<User> {
  const res = await fetch("/api/auth/profile", {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to update profile.");
  }

  const result = await res.json();
  return result.data || result;
}

export async function changePassword(data: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  const res = await fetch("/api/auth/password", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to change password.");
  }
}

export async function updateRecoveryEmail(recoveryEmail: string): Promise<{ message: string; user: User }> {
  const res = await fetch("/api/auth/recovery-email", {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ recovery_email: recoveryEmail }),
  });

  const result = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(result.message || "Failed to update recovery email.");
  return { message: result.message || "Verification code sent.", user: result.user?.data || result.user };
}

export async function verifyRecoveryEmail(code: string): Promise<{ message: string; user: User }> {
  const res = await fetch("/api/auth/recovery-email/verify", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ code }),
  });

  const result = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(result.message || "Failed to verify recovery email.");
  return { message: result.message || "Recovery email verified.", user: result.user?.data || result.user };
}

export async function requestPasswordReset(email: string): Promise<string> {
  const res = await fetch("/api/auth/forgot-password", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ email }),
  });

  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.message || "Failed to request password reset.");
  return data.message || "If that email exists, a password reset link has been sent.";
}

export async function resetPassword(data: {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}): Promise<string> {
  const res = await fetch("/api/auth/reset-password", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  const result = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(result.message || "Failed to reset password.");
  return result.message || "Password reset.";
}

export async function fetchCurrentUser(): Promise<User> {
  const res = await fetch("/api/auth/me", {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch user context.");
  }

  const data = await res.json();
  // Support both wrapped (data.data) and unwrapped (data) structures
  return data.data || data;
}

export async function completeOnboarding(data: { password: string; password_confirmation: string; recovery_email: string }): Promise<User> {
  const res = await fetch('/api/auth/onboarding', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(data) });
  const result = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(result.message || 'Failed to complete account setup.');
  return result.user?.data || result.user;
}

export async function skipOnboarding(): Promise<User> {
  const res = await fetch('/api/auth/onboarding/skip', { method: 'POST', headers: { Accept: 'application/json' } });
  const result = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(result.message || 'Failed to defer account setup.');
  return result.user?.data || result.user;
}
