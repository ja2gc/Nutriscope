export interface User {
  id: number;
  name: string;
  email: string;
  contact_number?: string | null;
  profile_photo?: string | null;
  role: "RND" | "FSS" | "Admin";
  is_active: boolean;
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
  name: string;
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
