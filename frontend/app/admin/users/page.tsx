"use client";

import React, { useEffect, useMemo, useState } from "react";
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
  Search,
  Filter,
  KeyRound,
  Edit2,
  Trash2,
  X,
  UserCheck,
  UserX,
  AlertTriangle,
  RefreshCw,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Badge, BadgeTone } from "@/components/ui/Badge";

const roleTones: Record<"Admin" | "RND" | "FSS", BadgeTone> = {
  Admin: "violet",
  RND: "emerald",
  FSS: "sky",
};

export default function UserManagementPage() {
  const { user: currentUser } = useAuth();
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Search & Filter
  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState<string>("All");

  // Modals
  const [formOpen, setFormOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [resetOpen, setResetOpen] = useState(false);
  const [resettingUser, setResettingUser] = useState<User | null>(null);

  // Form states
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<"Admin" | "RND" | "FSS">("RND");
  const [password, setPassword] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [formError, setFormError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // Password reset states
  const [newPassword, setNewPassword] = useState("");
  const [resetError, setResetError] = useState<string | null>(null);
  const [resetSuccess, setResetSuccess] = useState<boolean>(false);

  async function loadUsers() {
    try {
      setLoading(true);
      setError(null);
      const data = await listUsers();
      setUsers(data);
    } catch (err: any) {
      setError(err.message || "Failed to load directory.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadUsers();
  }, []);

  const filteredUsers = useMemo(() => {
    return users.filter((u) => {
      const matchesSearch =
        u.name.toLowerCase().includes(search.toLowerCase()) ||
        u.email.toLowerCase().includes(search.toLowerCase());
      const matchesRole = roleFilter === "All" || u.role === roleFilter;
      return matchesSearch && matchesRole;
    });
  }, [users, search, roleFilter]);

  function openCreate() {
    setEditingUser(null);
    setName("");
    setEmail("");
    setRole("RND");
    setPassword("");
    setIsActive(true);
    setFormError(null);
    setFormOpen(true);
  }

  function openEdit(u: User) {
    setEditingUser(u);
    setName(u.name);
    setEmail(u.email);
    setRole(u.role);
    setPassword("");
    setIsActive(u.is_active);
    setFormError(null);
    setFormOpen(true);
  }

  function openReset(u: User) {
    setResettingUser(u);
    setNewPassword("");
    setResetError(null);
    setResetSuccess(false);
    setResetOpen(true);
  }

  async function handleToggleActive(u: User) {
    try {
      if (u.id === currentUser?.id) {
        alert("You cannot deactivate your own administrative account.");
        return;
      }
      const updated = await setActive(u.id, !u.is_active);
      setUsers((prev) => prev.map((item) => (item.id === u.id ? updated : item)));
    } catch (err: any) {
      alert(err.message || "Failed to change user status.");
    }
  }

  async function handleDeleteUser(u: User) {
    if (u.id === currentUser?.id) {
      alert("You cannot delete your own account.");
      return;
    }
    if (!confirm(`Are you sure you want to delete account: ${u.name}?`)) {
      return;
    }
    try {
      await deleteUser(u.id);
      setUsers((prev) => prev.filter((item) => item.id !== u.id));
    } catch (err: any) {
      alert(err.message || "Failed to delete user.");
    }
  }

  async function handleSaveUser(e: React.FormEvent) {
    e.preventDefault();
    setFormError(null);
    setSubmitting(true);

    try {
      if (editingUser) {
        const payload: UpdateUserPayload = {
          name,
          email,
          role,
          is_active: isActive,
        };
        const updated = await updateUser(editingUser.id, payload);
        setUsers((prev) => prev.map((item) => (item.id === editingUser.id ? updated : item)));
        setFormOpen(false);
      } else {
        const payload: CreateUserPayload = {
          name,
          email,
          role,
          password,
          password_confirmation: password,
          is_active: isActive,
        };
        const created = await createUser(payload);
        setUsers((prev) => [created, ...prev]);
        setFormOpen(false);
      }
    } catch (err: any) {
      setFormError(err.message || "Failed to save user context.");
    } finally {
      setSubmitting(false);
    }
  }

  async function handleResetPassword(e: React.FormEvent) {
    e.preventDefault();
    if (!resettingUser) return;
    setResetError(null);
    setSubmitting(true);

    try {
      await resetPassword(resettingUser.id, newPassword);
      setResetSuccess(true);
      setTimeout(() => {
        setResetOpen(false);
      }, 1500);
    } catch (err: any) {
      setResetError(err.message || "Failed to reset password.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="space-y-6 font-sans text-zinc-100">
      {/* Breadcrumbs & Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 select-none">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-zinc-500">
            <span>Admin</span>
            <span className="text-zinc-700">/</span>
            <span className="text-zinc-400 font-bold">Directory Management</span>
          </div>
          <h1 className="text-xl font-extrabold text-white tracking-tight mt-1 flex items-center gap-2">
            <Users className="h-5 w-5 text-purple-400" />
            User & RBAC Manager
          </h1>
          <p className="text-xs text-zinc-400 mt-0.5">
            Administer accounts, role policies, active status, and password resets.
          </p>
        </div>

        <button
          onClick={openCreate}
          className="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer select-none"
        >
          <Plus className="h-4 w-4" />
          Create Account
        </button>
      </div>

      {/* Filters Bar */}
      <div className="bg-zinc-900 border border-zinc-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
        {/* Search */}
        <div className="relative w-full md:max-w-md">
          <span className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-500">
            <Search className="h-4 w-4" />
          </span>
          <input
            type="text"
            placeholder="Search by name or email address..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-4 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 placeholder:text-zinc-500 text-white"
          />
        </div>

        {/* Filters */}
        <div className="flex items-center gap-3 w-full md:w-auto shrink-0 select-none">
          <span className="text-xs font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5 shrink-0">
            <Filter className="h-3.5 w-3.5" />
            Role:
          </span>
          <div className="flex border border-zinc-850 bg-zinc-950 p-0.5 rounded-xl text-xs font-semibold">
            {["All", "Admin", "RND", "FSS"].map((r) => (
              <button
                key={r}
                onClick={() => setRoleFilter(r)}
                className={`px-3 py-1.5 rounded-lg cursor-pointer transition-colors ${
                  roleFilter === r
                    ? "bg-zinc-850 text-white font-bold"
                    : "text-zinc-400 hover:text-white"
                }`}
              >
                {r}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Directory Table */}
      {error ? (
        <div className="bg-red-950/30 border border-red-900/50 p-4 rounded-xl flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <div className="text-xs text-red-400 font-bold">Failed to load users</div>
            <div className="text-xs text-red-500/80 mt-0.5">{error}</div>
          </div>
        </div>
      ) : loading ? (
        <div className="bg-zinc-900 border border-zinc-800 rounded-3xl p-12 text-center flex flex-col items-center justify-center gap-3">
          <RefreshCw className="h-6 w-6 text-zinc-500 animate-spin" />
          <div className="text-xs text-zinc-400 font-semibold uppercase tracking-wider">
            Loading user list...
          </div>
        </div>
      ) : filteredUsers.length === 0 ? (
        <div className="bg-zinc-900 border border-zinc-800 rounded-3xl p-16 text-center max-w-xl mx-auto shadow-sm">
          <div className="p-3 bg-zinc-950 border border-zinc-850 rounded-2xl w-fit mx-auto text-zinc-600 mb-4">
            <Users className="h-8 w-8" />
          </div>
          <h3 className="text-sm font-bold text-zinc-300">No matching accounts found</h3>
          <p className="text-xs text-zinc-500 mt-1 max-w-sm mx-auto leading-relaxed">
            Try adjusting your filters or search criteria.
          </p>
        </div>
      ) : (
        <div className="bg-zinc-900 border border-zinc-800 rounded-3xl shadow-md overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[700px]">
              <thead>
                <tr className="bg-zinc-950 border-b border-zinc-850">
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                    User Details
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                    Role Privilege
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider text-right">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-850 bg-zinc-900/40">
                {filteredUsers.map((u, idx) => {
                  const isSelf = u.id === currentUser?.id;
                  
                  return (
                    <tr
                      key={u.id}
                      className={`${
                        idx % 2 === 0 ? "bg-zinc-900/20" : "bg-zinc-900/60"
                      } hover:bg-zinc-850/30 transition-colors`}
                    >
                      {/* User details */}
                      <td className="px-5 py-3.5">
                        <div className="text-xs font-bold text-white flex items-center gap-1.5">
                          {u.name}
                          {isSelf && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded bg-purple-500/10 text-purple-400 border border-purple-500/20 font-bold uppercase tracking-wider">
                              Self
                            </span>
                          )}
                        </div>
                        <div className="text-[10px] text-zinc-400 font-mono mt-0.5">{u.email}</div>
                      </td>

                      {/* Role */}
                      <td className="px-5 py-3.5">
                        <Badge tone={roleTones[u.role] || "zinc"}>{u.role}</Badge>
                      </td>

                      {/* Status */}
                      <td className="px-5 py-3.5">
                        <button
                          onClick={() => handleToggleActive(u)}
                          disabled={isSelf}
                          className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-[10px] font-bold uppercase tracking-wider transition-colors select-none ${
                            isSelf ? "cursor-not-allowed opacity-50" : "cursor-pointer"
                          } ${
                            u.is_active
                              ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20"
                              : "bg-zinc-800 text-zinc-400 border-zinc-700 hover:bg-zinc-700 hover:text-white"
                          }`}
                        >
                          {u.is_active ? (
                            <>
                              <UserCheck className="h-3 w-3" />
                              Active
                            </>
                          ) : (
                            <>
                              <UserX className="h-3 w-3" />
                              Suspended
                            </>
                          )}
                        </button>
                      </td>

                      {/* Actions */}
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          {/* Reset password */}
                          <button
                            onClick={() => openReset(u)}
                            title="Reset password"
                            className="p-1.5 rounded-lg border border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-amber-400 hover:border-amber-500/20 transition-all cursor-pointer"
                          >
                            <KeyRound className="h-3.5 w-3.5" />
                          </button>

                          {/* Edit details */}
                          <button
                            onClick={() => openEdit(u)}
                            title="Edit details"
                            className="p-1.5 rounded-lg border border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-white hover:border-zinc-600 transition-all cursor-pointer"
                          >
                            <Edit2 className="h-3.5 w-3.5" />
                          </button>

                          {/* Delete (only non-self) */}
                          <button
                            onClick={() => handleDeleteUser(u)}
                            disabled={isSelf}
                            title={isSelf ? "Cannot delete self" : "Delete account"}
                            className={`p-1.5 rounded-lg border border-zinc-800 bg-zinc-950 transition-all ${
                              isSelf
                                ? "text-zinc-700 cursor-not-allowed opacity-55"
                                : "text-zinc-500 hover:text-red-500 hover:border-red-500/20 cursor-pointer"
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
        </div>
      )}

      {/* Form Dialog Modal (Create / Edit User) */}
      {formOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm"
          onClick={() => setFormOpen(false)}
        >
          <div
            className="w-full max-w-md bg-zinc-900 border border-zinc-850 rounded-3xl overflow-hidden shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-zinc-850 bg-zinc-950 flex items-center justify-between gap-4 select-none">
              <div>
                <h3 className="text-xs font-bold text-white uppercase tracking-[0.18em]">
                  {editingUser ? "Edit User Account" : "Register New Account"}
                </h3>
                <p className="text-[10px] text-zinc-500 mt-1">
                  Specify details below. Password rules enforced on database level.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setFormOpen(false)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 text-[10px] font-bold uppercase tracking-wider text-zinc-450 hover:text-white hover:bg-zinc-850 transition-all cursor-pointer"
              >
                <X className="h-3.5 w-3.5" />
                Close
              </button>
            </div>

            <form onSubmit={handleSaveUser} className="p-5 space-y-4">
              {formError && (
                <div className="text-xs font-semibold text-red-400 bg-red-950/20 border border-red-900/50 rounded-xl px-3 py-2 flex items-start gap-2">
                  <AlertTriangle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                  <span>{formError}</span>
                </div>
              )}

              {/* Full Name */}
              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                  Full Name
                </label>
                <input
                  type="text"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="e.g. Dr. Jane Doe"
                  className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 placeholder:text-zinc-650"
                />
              </div>

              {/* Email Address */}
              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                  Email Address
                </label>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="name@hospital.local"
                  className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 placeholder:text-zinc-650"
                />
              </div>

              {/* Privilege Role & Active Toggle Grid */}
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Privilege Role
                  </label>
                  <select
                    value={role}
                    onChange={(e) => setRole(e.target.value as any)}
                    className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
                  >
                    <option value="RND">Registered Dietitian (RND)</option>
                    <option value="FSS">Food Service Supervisor (FSS)</option>
                    <option value="Admin">System Admin</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Status State
                  </label>
                  <select
                    value={isActive ? "true" : "false"}
                    onChange={(e) => setIsActive(e.target.value === "true")}
                    disabled={editingUser?.id === currentUser?.id}
                    className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <option value="true">Active Access</option>
                    <option value="false">Suspended Access</option>
                  </select>
                </div>
              </div>

              {/* Password (only for creation) */}
              {!editingUser && (
                <div className="space-y-1.5 border-t border-zinc-850/60 pt-3.5">
                  <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Temporary Password
                  </label>
                  <input
                    type="password"
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Min. 8 chars, numbers & specials"
                    className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 placeholder:text-zinc-650"
                  />
                </div>
              )}

              {/* Actions Footer */}
              <div className="flex gap-2.5 pt-3 select-none">
                <Button
                  variant="primary"
                  loading={submitting}
                  className="px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider w-full bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white"
                >
                  {editingUser ? "Save Updates" : "Create User"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Password Reset Modal */}
      {resetOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm"
          onClick={() => setResetOpen(false)}
        >
          <div
            className="w-full max-w-sm bg-zinc-900 border border-zinc-850 rounded-3xl overflow-hidden shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-zinc-850 bg-zinc-950 flex items-center justify-between gap-4 select-none">
              <div>
                <h3 className="text-xs font-bold text-white uppercase tracking-[0.18em]">
                  Reset User Password
                </h3>
                <p className="text-[10px] text-zinc-500 mt-1">
                  Overwrite password credentials for: {resettingUser?.name}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setResetOpen(false)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 text-[10px] font-bold uppercase tracking-wider text-zinc-450 hover:text-white hover:bg-zinc-850 transition-all cursor-pointer"
              >
                <X className="h-3.5 w-3.5" />
                Close
              </button>
            </div>

            <form onSubmit={handleResetPassword} className="p-5 space-y-4">
              {resetError && (
                <div className="text-xs font-semibold text-red-400 bg-red-950/20 border border-red-900/50 rounded-xl px-3 py-2 flex items-start gap-2">
                  <AlertTriangle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                  <span>{resetError}</span>
                </div>
              )}

              {resetSuccess && (
                <div className="text-xs font-semibold text-emerald-400 bg-emerald-950/20 border border-emerald-900/50 rounded-xl px-3 py-2">
                  Password has been reset successfully. Modal closing...
                </div>
              )}

              {/* Password Input */}
              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                  New Password
                </label>
                <input
                  type="password"
                  required
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  placeholder="Min. 8 chars, numbers & specials"
                  className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 placeholder:text-zinc-650"
                />
              </div>

              {/* Actions Footer */}
              <div className="flex gap-2.5 pt-2 select-none">
                <Button
                  variant="primary"
                  loading={submitting}
                  disabled={resetSuccess}
                  className="px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider w-full bg-amber-600 hover:bg-amber-700 active:bg-amber-850 text-white"
                >
                  {submitting ? "Resetting..." : "Reset User Password"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
