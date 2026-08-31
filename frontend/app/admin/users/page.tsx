"use client";

import React, { useEffect, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import {
  listUsers,
  createUser,
  updateUser,
  deleteUser,
  resetPassword,
  setActive,
  CreateUserPayload,
  UpdateUserPayload,
} from "@/services/adminUserService";
import { User } from "@/services/authService";
import {
  Users,
  Plus,
  Filter,
  KeyRound,
  Edit2,
  Trash2,
  X,
  UserCheck,
  UserX,
  AlertTriangle,
  RefreshCw,
  CheckCircle2,
} from "lucide-react";
import SearchInput from "@/components/ui/SearchInput";
import { Button } from "@/components/ui/Button";
import { Badge, BadgeTone } from "@/components/ui/Badge";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import {
  changedPersonNameFields,
  personDisplayName,
  personNameFormValues,
  requiredPersonNameFields,
} from "@/lib/personName";

// ─── helpers ────────────────────────────────────────────────────────────────

const roleTones: Record<"Admin" | "RND" | "FSS", BadgeTone> = {
  Admin: "violet",
  RND: "emerald",
  FSS: "sky",
};

type FieldErrors = Record<string, string[]>;

function FieldError({ errors, field }: { errors: FieldErrors; field: string }) {
  const msgs = errors[field];
  if (!msgs?.length) return null;
  return (
    <p className="text-xs text-red-600 font-semibold mt-1">{msgs[0]}</p>
  );
}

function inputCls(errors: FieldErrors, field: string) {
  const hasErr = !!errors[field]?.length;
  return `min-h-11 w-full px-3 py-2 text-base border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus-visible:ring-2 ${
    hasErr
      ? "border-red-400 bg-red-50/40"
      : "border-warm-200 bg-white focus:border-emerald-500"
  } text-warm-800 placeholder:text-warm-400`;
}

function hasFieldErrors(error: unknown): error is Error & { fieldErrors: FieldErrors } {
  return error instanceof Error && "fieldErrors" in error;
}

// ─── page ───────────────────────────────────────────────────────────────────

export default function UserManagementPage() {
  const { user: currentUser } = useAuth();
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Search & Filter
  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState<string>("All");
  const [page, setPage] = useState(1);
  const [usersMeta, setUsersMeta] = useState<PaginationMeta | null>(null);

  // Create / Edit modal
  const [formOpen, setFormOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<"Admin" | "RND" | "FSS">("RND");
  const [password, setPassword] = useState("");
  const [passwordConfirm, setPasswordConfirm] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [formError, setFormError] = useState<string | null>(null);
  const [formFieldErrors, setFormFieldErrors] = useState<FieldErrors>({});
  const [submitting, setSubmitting] = useState(false);

  // Reset-password modal
  const [resetOpen, setResetOpen] = useState(false);
  const [resettingUser, setResettingUser] = useState<User | null>(null);
  const [newPassword, setNewPassword] = useState("");
  const [newPasswordConfirm, setNewPasswordConfirm] = useState("");
  const [resetError, setResetError] = useState<string | null>(null);
  const [resetFieldErrors, setResetFieldErrors] = useState<FieldErrors>({});
  const [resetSuccess, setResetSuccess] = useState(false);

  // ── data ──────────────────────────────────────────────────────────────────

  async function loadUsers() {
    try {
      setLoading(true);
      setError(null);
      const result = await listUsers({ page, search, role: roleFilter });
      setUsers(result.data);
      setUsersMeta(result.meta);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to load users.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const timer = setTimeout(() => { void loadUsers(); }, 250);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, search, roleFilter]);

  useEffect(() => {
    setPage(1);
  }, [search, roleFilter]);

  const pagedUsers = users;

  // ── modal openers ─────────────────────────────────────────────────────────

  function openCreate() {
    setEditingUser(null);
    setFirstName("");
    setLastName("");
    setEmail("");
    setRole("RND");
    setPassword("");
    setPasswordConfirm("");
    setIsActive(true);
    setFormError(null);
    setFormFieldErrors({});
    setFormOpen(true);
  }

  function openEdit(u: User) {
    setEditingUser(u);
    const nameValues = personNameFormValues(u);
    setFirstName(nameValues.firstName);
    setLastName(nameValues.lastName);
    setEmail(u.email);
    setRole(u.role);
    setPassword("");
    setPasswordConfirm("");
    setIsActive(u.is_active);
    setFormError(null);
    setFormFieldErrors({});
    setFormOpen(true);
  }

  function openReset(u: User) {
    setResettingUser(u);
    setNewPassword("");
    setNewPasswordConfirm("");
    setResetError(null);
    setResetFieldErrors({});
    setResetSuccess(false);
    setResetOpen(true);
  }

  // ── handlers ──────────────────────────────────────────────────────────────

  async function handleToggleActive(u: User) {
    if (u.id === currentUser?.id) {
      alert("You cannot deactivate your own account.");
      return;
    }
    try {
      const updated = await setActive(u.id, !u.is_active);
      setUsers((prev) => prev.map((item) => (item.id === u.id ? updated : item)));
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to change user status.");
    }
  }

  async function handleDeleteUser(u: User) {
    if (u.id === currentUser?.id) {
      alert("You cannot delete your own account.");
      return;
    }
    if (!confirm(`Delete account for ${personDisplayName(u)}? This cannot be undone.`)) return;
    try {
      await deleteUser(u.id);
      setUsers((prev) => prev.filter((item) => item.id !== u.id));
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to delete user.");
    }
  }

  async function handleSaveUser(e: React.FormEvent) {
    e.preventDefault();
    setFormError(null);
    setFormFieldErrors({});
    setSubmitting(true);
    try {
      if (editingUser) {
        const nameFields = changedPersonNameFields(editingUser, firstName, lastName);
        const payload: UpdateUserPayload = {
          email,
          role,
          is_active: isActive,
          ...(nameFields ?? {}),
        };
        if (password) {
          payload.password = password;
          payload.password_confirmation = passwordConfirm;
        }
        const updated = await updateUser(editingUser.id, payload);
        setUsers((prev) =>
          prev.map((item) => (item.id === editingUser.id ? updated : item))
        );
        setFormOpen(false);
      } else {
        const nameFields = requiredPersonNameFields(firstName, lastName);
        const payload: CreateUserPayload = {
          ...nameFields,
          email,
          role,
          password,
          password_confirmation: passwordConfirm,
          is_active: isActive,
        };
        const created = await createUser(payload);
        setUsers((prev) => [created, ...prev]);
        setFormOpen(false);
      }
    } catch (err: unknown) {
      if (hasFieldErrors(err)) {
        setFormFieldErrors(err.fieldErrors);
        setFormError(err.message || "Please fix the errors below.");
      } else if (err instanceof Error) {
        setFormError(err.message || "Failed to save user.");
      } else {
        setFormError("Failed to save user.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function handleResetPassword(e: React.FormEvent) {
    e.preventDefault();
    if (!resettingUser) return;
    setResetError(null);
    setResetFieldErrors({});
    setSubmitting(true);
    try {
      await resetPassword(resettingUser.id, newPassword, newPasswordConfirm);
      setResetSuccess(true);
      setTimeout(() => setResetOpen(false), 1500);
    } catch (err: unknown) {
      if (hasFieldErrors(err)) {
        setResetFieldErrors(err.fieldErrors);
        setResetError(err.message || "Please fix the errors below.");
      } else if (err instanceof Error) {
        setResetError(err.message || "Failed to reset password.");
      } else {
        setResetError("Failed to reset password.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  // ── render ────────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6 font-sans">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
            <span>Admin</span>
            <span>/</span>
            <span className="text-warm-600 font-bold">User & RBAC Manager</span>
          </div>
          <h1 className="text-xl font-extrabold text-warm-900 tracking-tight mt-1 flex items-center gap-2">
            <Users className="h-5 w-5 text-emerald-600" />
            User & RBAC Manager
          </h1>
          <p className="text-sm text-warm-500 mt-0.5">
            Manage accounts, roles, active status, and password resets.
          </p>
        </div>

        <button
          onClick={openCreate}
          className="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-base font-semibold rounded-lg transition-colors cursor-pointer select-none shrink-0"
        >
          <Plus className="h-4 w-4" />
          Create Account
        </button>
      </div>

      {/* Filters Bar */}
      <div className="bg-white border border-warm-200 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
        <SearchInput className="md:max-w-sm" label="Search users" value={search} onChange={setSearch} loading={loading} />

        <div className="flex items-center gap-3 shrink-0 select-none">
          <span className="text-sm font-bold text-warm-500 uppercase tracking-wider flex items-center gap-1.5">
            <Filter className="h-3.5 w-3.5" />
            Role:
          </span>
          <div className="flex border border-warm-200 bg-warm-50 p-0.5 rounded-lg text-sm font-semibold gap-0.5">
            {["All", "Admin", "RND", "FSS"].map((r) => (
              <button
                key={r}
                onClick={() => setRoleFilter(r)}
                className={`px-3 py-1.5 rounded-md cursor-pointer transition-colors ${
                  roleFilter === r
                    ? "bg-white shadow-sm text-warm-900 font-bold border border-warm-200"
                    : "text-warm-500 hover:text-warm-800"
                }`}
              >
                {r}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Table / states */}
      {error ? (
        <div className="bg-red-50 border border-red-200 p-4 rounded-xl flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <div className="text-sm text-red-700 font-bold">Failed to load users</div>
            <div className="text-sm text-red-600 mt-0.5">{error}</div>
            <button
              onClick={loadUsers}
              className="mt-2 text-sm text-red-700 underline hover:no-underline cursor-pointer"
            >
              Retry
            </button>
          </div>
        </div>
      ) : loading ? (
        <div className="bg-white border border-warm-200 rounded-2xl p-12 text-center flex flex-col items-center justify-center gap-3 shadow-sm">
          <RefreshCw className="h-6 w-6 text-emerald-600 animate-spin" />
          <div className="text-sm text-warm-500 font-semibold uppercase tracking-wider">
            Loading users…
          </div>
        </div>
      ) : users.length === 0 ? (
        <div className="bg-white border border-warm-200 rounded-2xl p-16 text-center shadow-sm">
          <div className="p-3 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400 mb-4">
            <Users className="h-8 w-8" />
          </div>
          <h3 className="text-base font-bold text-warm-700">No accounts found</h3>
          <p className="text-sm text-warm-400 mt-1">
            {search || roleFilter !== "All"
              ? "Try adjusting your search or filter."
              : "Create the first account using the button above."}
          </p>
        </div>
      ) : (
        <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left min-w-[640px]">
              <thead className="bg-warm-50 border-b border-warm-100">
                <tr>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider">
                    User
                  </th>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider">
                    Role
                  </th>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider text-right">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {pagedUsers.map((u) => {
                  const isSelf = u.id === currentUser?.id;
                  return (
                    <tr key={u.id} className="hover:bg-warm-50/60 transition-colors">
                      {/* User */}
                      <td className="px-5 py-3.5">
                        <div className="flex items-center gap-1.5">
                          <span className="text-base font-semibold text-warm-800">
                            {personDisplayName(u)}
                          </span>
                          {isSelf && (
                            <span className="text-xs px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold uppercase tracking-wider">
                              You
                            </span>
                          )}
                        </div>
                        <div className="text-sm text-warm-400 font-mono mt-0.5">
                          {u.email}
                        </div>
                      </td>

                      {/* Role */}
                      <td className="px-5 py-3.5">
                        <Badge tone={roleTones[u.role] ?? "zinc"}>{u.role}</Badge>
                      </td>

                      {/* Status toggle */}
                      <td className="px-5 py-3.5">
                        <button
                          onClick={() => handleToggleActive(u)}
                          disabled={isSelf}
                          title={isSelf ? "Cannot change own status" : u.is_active ? "Deactivate" : "Activate"}
                          className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-bold uppercase tracking-wider transition-colors select-none ${
                            isSelf ? "cursor-not-allowed opacity-50" : "cursor-pointer"
                          } ${
                            u.is_active
                              ? "bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100"
                              : "bg-warm-100 text-warm-500 border-warm-200 hover:bg-warm-200 hover:text-warm-700"
                          }`}
                        >
                          {u.is_active ? (
                            <><UserCheck className="h-3 w-3" /> Active</>
                          ) : (
                            <><UserX className="h-3 w-3" /> Suspended</>
                          )}
                        </button>
                      </td>

                      {/* Actions */}
                      <td className="px-5 py-3.5">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            onClick={() => openReset(u)}
                            title="Reset password"
                            className="p-1.5 rounded-lg border border-warm-200 text-warm-400 hover:text-amber-600 hover:border-amber-300 hover:bg-amber-50 transition-all cursor-pointer"
                          >
                            <KeyRound className="h-3.5 w-3.5" />
                          </button>
                          <button
                            onClick={() => openEdit(u)}
                            title="Edit account"
                            className="p-1.5 rounded-lg border border-warm-200 text-warm-400 hover:text-warm-700 hover:border-warm-300 hover:bg-warm-50 transition-all cursor-pointer"
                          >
                            <Edit2 className="h-3.5 w-3.5" />
                          </button>
                          <button
                            onClick={() => handleDeleteUser(u)}
                            disabled={isSelf}
                            title={isSelf ? "Cannot delete own account" : "Delete account"}
                            className={`p-1.5 rounded-lg border border-warm-200 transition-all ${
                              isSelf
                                ? "text-warm-300 cursor-not-allowed"
                                : "text-warm-400 hover:text-red-600 hover:border-red-300 hover:bg-red-50 cursor-pointer"
                            }`}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <Pagination meta={usersMeta} page={page} onPageChange={setPage} />
        </div>
      )}

      {/* ── Create / Edit Modal ─────────────────────────────────────────────── */}
      {formOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/20 backdrop-blur-sm"
          onClick={() => setFormOpen(false)}
        >
          <div
            className="w-full max-w-md bg-white border border-warm-200 rounded-2xl overflow-hidden shadow-xl"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal header */}
            <div className="px-5 py-4 border-b border-warm-100 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-base font-bold text-warm-900">
                  {editingUser ? "Edit Account" : "Create Account"}
                </h3>
                <p className="text-sm text-warm-400 mt-0.5">
                  {editingUser
                    ? "Update details. Leave password blank to keep existing."
                    : "Fill in all required fields."}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setFormOpen(false)}
                className="p-1.5 rounded-lg border border-warm-200 text-warm-400 hover:text-warm-700 hover:bg-warm-50 transition-all cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Modal body */}
            <form onSubmit={handleSaveUser} className="p-5 space-y-4">
              {/* General error banner */}
              {formError && (
                <div className="text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl px-3 py-2.5 flex items-start gap-2">
                  <AlertTriangle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                  <span>{formError}</span>
                </div>
              )}

              {/* Name */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label htmlFor="account-first-name" className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
                    First Name {!editingUser && <span className="text-red-500">*</span>}
                  </label>
                  <input
                    id="account-first-name"
                    type="text"
                    required={!editingUser}
                    value={firstName}
                    onChange={(e) => setFirstName(e.target.value)}
                    className={inputCls(formFieldErrors, "first_name")}
                  />
                  <FieldError errors={formFieldErrors} field="first_name" />
                </div>
                <div>
                  <label htmlFor="account-last-name" className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
                    Last Name {!editingUser && <span className="text-red-500">*</span>}
                  </label>
                  <input
                    id="account-last-name"
                    type="text"
                    required={!editingUser}
                    value={lastName}
                    onChange={(e) => setLastName(e.target.value)}
                    className={inputCls(formFieldErrors, "last_name")}
                  />
                  <FieldError errors={formFieldErrors} field="last_name" />
                </div>
              </div>

              {/* Email */}
              <div>
                <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
                  Email Address <span className="text-red-500">*</span>
                </label>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className={inputCls(formFieldErrors, "email")}
                />
                <FieldError errors={formFieldErrors} field="email" />
              </div>

              {/* Role + Active grid */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
                    Role <span className="text-red-500">*</span>
                  </label>
                  <select
                    value={role}
                    onChange={(e) => setRole(e.target.value as "Admin" | "RND" | "FSS")}
                    className={inputCls(formFieldErrors, "role")}
                  >
                    <option value="RND">RND — Dietitian</option>
                    <option value="FSS">FSS — Food Service</option>
                    <option value="Admin">Admin</option>
                  </select>
                  <FieldError errors={formFieldErrors} field="role" />
                </div>

                <div>
                  <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
                    Status
                  </label>
                  <select
                    value={isActive ? "true" : "false"}
                    onChange={(e) => setIsActive(e.target.value === "true")}
                    disabled={editingUser?.id === currentUser?.id}
                    className={`${inputCls(formFieldErrors, "is_active")} disabled:opacity-50 disabled:cursor-not-allowed`}
                  >
                    <option value="true">Active</option>
                    <option value="false">Suspended</option>
                  </select>
                </div>
              </div>

              {/* Password section */}
              <div className="border-t border-warm-100 pt-4 space-y-4">
                <p className="text-xs font-bold text-warm-500 uppercase tracking-wider">
                  {editingUser ? "New Password (optional)" : "Password"}
                  {!editingUser && <span className="text-red-500 ml-0.5">*</span>}
                </p>

                <div>
                  <label className="block text-xs text-warm-500 mb-1">
                    {editingUser ? "New password" : "Password"}
                  </label>
                  <input
                    type="password"
                    required={!editingUser}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className={inputCls(formFieldErrors, "password")}
                  />
                  <FieldError errors={formFieldErrors} field="password" />
                </div>

                <div>
                  <label className="block text-xs text-warm-500 mb-1">
                    Confirm password
                  </label>
                  <input
                    type="password"
                    required={!editingUser || !!password}
                    value={passwordConfirm}
                    onChange={(e) => setPasswordConfirm(e.target.value)}
                    className={inputCls(formFieldErrors, "password_confirmation")}
                  />
                  <FieldError errors={formFieldErrors} field="password_confirmation" />
                </div>
              </div>

              {/* Submit */}
              <div className="pt-1">
                <Button variant="primary" loading={submitting} type="submit" fullWidth>
                  {editingUser ? "Save Changes" : "Create Account"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── Reset Password Modal ────────────────────────────────────────────── */}
      {resetOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/20 backdrop-blur-sm"
          onClick={() => setResetOpen(false)}
        >
          <div
            className="w-full max-w-sm bg-white border border-warm-200 rounded-2xl overflow-hidden shadow-xl"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal header */}
            <div className="px-5 py-4 border-b border-warm-100 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-base font-bold text-warm-900">Reset Password</h3>
                <p className="text-sm text-warm-400 mt-0.5">
                  Set a new password for{" "}
                  <span className="font-semibold text-warm-600">{personDisplayName(resettingUser)}</span>
                </p>
              </div>
              <button
                type="button"
                onClick={() => setResetOpen(false)}
                className="p-1.5 rounded-lg border border-warm-200 text-warm-400 hover:text-warm-700 hover:bg-warm-50 transition-all cursor-pointer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Modal body */}
            <form onSubmit={handleResetPassword} className="p-5 space-y-4">
              {resetError && !resetSuccess && (
                <div className="text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl px-3 py-2.5 flex items-start gap-2">
                  <AlertTriangle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                  <span>{resetError}</span>
                </div>
              )}

              {resetSuccess && (
                <div className="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2.5 flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
                  Password reset successfully. Closing…
                </div>
              )}

              {!resetSuccess && (
                <>
                  <div>
                    <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
                      New Password <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="password"
                      required
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      className={inputCls(resetFieldErrors, "password")}
                    />
                    <FieldError errors={resetFieldErrors} field="password" />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
                      Confirm Password <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="password"
                      required
                      value={newPasswordConfirm}
                      onChange={(e) => setNewPasswordConfirm(e.target.value)}
                      className={inputCls(resetFieldErrors, "password_confirmation")}
                    />
                    <FieldError errors={resetFieldErrors} field="password_confirmation" />
                  </div>

                  <div className="pt-1">
                    <Button
                      variant="primary"
                      loading={submitting}
                      type="submit"
                      fullWidth
                      className="!bg-amber-600 hover:!bg-amber-700 active:!bg-amber-800"
                    >
                      Reset Password
                    </Button>
                  </div>
                </>
              )}
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
