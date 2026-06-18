import { apiFetch } from "@/lib/apiFetch";
import { User } from "@/services/authService";

export interface CreateUserPayload {
  name: string;
  email: string;
  role: "RND" | "FSS" | "Admin";
  password?: string;
  password_confirmation?: string;
  is_active?: boolean;
}

export interface UpdateUserPayload {
  name?: string;
  email?: string;
  role?: "RND" | "FSS" | "Admin";
  password?: string;
  password_confirmation?: string;
  is_active?: boolean;
}

export async function listUsers(): Promise<User[]> {
  const res = await apiFetch("/api/admin/users", {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch users.");
  }

  const responseData = await res.json();
  return responseData.data || [];
}

export async function createUser(data: CreateUserPayload): Promise<User> {
  const res = await apiFetch("/api/admin/users", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to create user.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function updateUser(id: number | string, data: UpdateUserPayload): Promise<User> {
  const res = await apiFetch(`/api/admin/users/${id}`, {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to update user.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function deleteUser(id: number | string): Promise<void> {
  const res = await apiFetch(`/api/admin/users/${id}`, {
    method: "DELETE",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to delete user.");
  }
}

export async function resetPassword(id: number | string, password: string): Promise<void> {
  const res = await apiFetch(`/api/admin/users/${id}/reset-password`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      password,
      password_confirmation: password,
    }),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to reset password.");
  }
}

export async function setActive(id: number | string, isActive: boolean): Promise<User> {
  return updateUser(id, { is_active: isActive });
}
