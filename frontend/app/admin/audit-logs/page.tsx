"use client";

import React, { useEffect, useState } from "react";
import { listAuditLogs, AuditLog, ListAuditLogsParams } from "@/services/auditLogService";
import { listUsers } from "@/services/adminUserService";
import { User } from "@/services/authService";
import type { PaginationMeta } from "@/components/ui/Pagination";
import { Pagination } from "@/components/ui/Pagination";
import {
  Activity,
  Filter,
  RefreshCw,
  AlertTriangle,
  ChevronDown,
  ChevronUp,
  Shield,
} from "lucide-react";
import { Badge, BadgeTone } from "@/components/ui/Badge";

const eventTones: Record<string, BadgeTone> = {
  created: "emerald",
  updated: "sky",
  deleted: "red",
  login: "violet",
  logout: "zinc",
  login_failed: "red",
  password_changed: "amber",
  password_reset: "amber",
};

const roleTones: Record<string, BadgeTone> = {
  Admin: "violet",
  RND: "emerald",
  FSS: "sky",
};

const outcomeTones: Record<string, BadgeTone> = {
  success: "emerald",
  failure: "red",
  blocked: "amber",
};

function formatDetailValue(value: string | number | string[] | null) {
  if (Array.isArray(value)) return value.join(", ");
  if (value === null) return "—";
  return String(value);
}

function formatChangeValue(value: string | number | boolean | null, redacted: boolean) {
  if (redacted) return "Redacted";
  if (value === null) return "—";
  return String(value);
}

export default function AuditLogsPage() {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [meta, setMeta] = useState<PaginationMeta>({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [page, setPage] = useState(1);
  const [actorId, setActorId] = useState<string>("All");
  const [domainFilter, setDomainFilter] = useState<string>("All");
  const [actionFilter, setActionFilter] = useState<string>("All");
  const [startDate, setStartDate] = useState<string>("");
  const [endDate, setEndDate] = useState<string>("");

  // Expandable row state
  const [expandedLogId, setExpandedLogId] = useState<string | null>(null);

  async function loadDirectoryData() {
    try {
      const u = await listUsers();
      setUsers(u);
    } catch {
      // best-effort for actor dropdown
    }
  }

  async function loadLogs() {
    try {
      setLoading(true);
      setError(null);

      const params: ListAuditLogsParams = { page, per_page: 15 };
      if (actorId !== "All") params.actor_id = actorId;
      if (domainFilter !== "All") params.domain = domainFilter as ListAuditLogsParams["domain"];
      if (actionFilter !== "All") params.action = actionFilter;
      if (startDate) params.start = startDate;
      if (endDate) params.end = endDate;

      const response = await listAuditLogs(params);
      setLogs(response.data);
      setMeta(response.meta);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to fetch audit log records.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadDirectoryData();
  }, []);

  useEffect(() => {
    void loadLogs();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, actorId, domainFilter, actionFilter, startDate, endDate]);

  function handleResetFilters() {
    setActorId("All");
    setDomainFilter("All");
    setActionFilter("All");
    setStartDate("");
    setEndDate("");
    setPage(1);
  }

  function toggleRow(logId: string) {
    setExpandedLogId((prev) => (prev === logId ? null : logId));
  }

  // shared input classes matching admin/users pattern
  const selCls =
    "w-full px-3 py-2 text-base border border-warm-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 text-warm-800";

  return (
    <div className="space-y-6 font-sans">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
            <span>Admin</span>
            <span>/</span>
            <span className="text-warm-600 font-bold">Audit Logs</span>
          </div>
          <h1 className="text-xl font-extrabold text-warm-900 tracking-tight mt-1 flex items-center gap-2">
            <Shield className="h-5 w-5 text-emerald-600" />
            System Audit Log
          </h1>
          <p className="text-sm text-warm-500 mt-0.5">
            Paginated ledger of all create, update, delete, and login events across the system.
          </p>
        </div>

        <button
          onClick={() => void loadLogs()}
          disabled={loading}
          className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-warm-200 bg-white text-sm font-semibold text-warm-600 hover:text-warm-900 hover:bg-warm-50 transition-colors disabled:opacity-50 select-none cursor-pointer shrink-0"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} />
          Refresh
        </button>
      </div>

      {/* Filter bar */}
      <div className="bg-white border border-warm-200 rounded-2xl p-4 shadow-sm">
        <div className="flex items-center gap-1.5 text-sm font-bold text-warm-500 uppercase tracking-wider mb-3 select-none">
          <Filter className="h-3.5 w-3.5" />
          Filters
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
          {/* Actor */}
          <div>
            <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
              Actor
            </label>
            <select
              value={actorId}
              onChange={(e) => { setActorId(e.target.value); setPage(1); }}
              className={selCls}
            >
              <option value="All">All actors</option>
              {users.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name} ({u.role})
                </option>
              ))}
            </select>
          </div>

          {/* Domain */}
          <div>
            <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
              Domain
            </label>
            <select
              value={domainFilter}
              onChange={(e) => { setDomainFilter(e.target.value); setPage(1); }}
              className={selCls}
            >
              <option value="All">All domains</option>
              <option value="accounts">Accounts</option>
              <option value="patients">Patients</option>
              <option value="ncp">NCP</option>
              <option value="reports">Reports</option>
              <option value="budget">Budget</option>
              <option value="procurement">Procurement</option>
              <option value="food_service">Food service</option>
              <option value="system">System</option>
            </select>
          </div>

          {/* Event */}
          <div>
            <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
              Event
            </label>
            <select
              value={actionFilter}
              onChange={(e) => { setActionFilter(e.target.value); setPage(1); }}
              className={selCls}
            >
              <option value="All">All events</option>
              <option value="created">Created</option>
              <option value="updated">Updated</option>
              <option value="deleted">Deleted</option>
              <option value="login_succeeded">Login Succeeded</option>
              <option value="logout">Logout</option>
              <option value="login_failed">Login Failed</option>
              <option value="password_changed">Password Changed</option>
              <option value="password_reset">Password Reset</option>
            </select>
          </div>

          {/* Start date */}
          <div>
            <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
              From
            </label>
            <input
              type="date"
              value={startDate}
              onChange={(e) => { setStartDate(e.target.value); setPage(1); }}
              className={selCls}
            />
          </div>

          {/* End date */}
          <div>
            <label className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1">
              To
            </label>
            <input
              type="date"
              value={endDate}
              onChange={(e) => { setEndDate(e.target.value); setPage(1); }}
              className={selCls}
            />
          </div>
        </div>

        <div className="flex justify-between items-center mt-3 pt-3 border-t border-warm-100 select-none">
          <span className="text-xs text-warm-400 font-medium">
            {meta.total} total {meta.total === 1 ? "entry" : "entries"} &mdash; page {meta.current_page} of {meta.last_page}
          </span>
          <button
            onClick={handleResetFilters}
            className="text-xs text-warm-400 hover:text-warm-700 font-semibold uppercase tracking-wider transition-colors cursor-pointer"
          >
            Clear filters
          </button>
        </div>
      </div>

      {/* Table / states */}
      {error ? (
        <div className="bg-red-50 border border-red-200 p-4 rounded-xl flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <div className="text-sm text-red-700 font-bold">Failed to load audit logs</div>
            <div className="text-sm text-red-600 mt-0.5">{error}</div>
            <button
              onClick={() => void loadLogs()}
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
            Loading audit logs...
          </div>
        </div>
      ) : logs.length === 0 ? (
        <div className="bg-white border border-warm-200 rounded-2xl p-16 text-center shadow-sm">
          <div className="p-3 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400 mb-4">
            <Activity className="h-8 w-8" />
          </div>
          <h3 className="text-base font-bold text-warm-700">No audit entries found</h3>
          <p className="text-sm text-warm-400 mt-1 max-w-sm mx-auto">
            No entries match the active filter configuration. Try adjusting or clearing filters.
          </p>
        </div>
      ) : (
        <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left min-w-[900px]">
              <thead className="bg-warm-50 border-b border-warm-100">
                <tr>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider">
                    When
                  </th>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider">
                    Event
                  </th>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider">
                    Actor
                  </th>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider">
                    Subject
                  </th>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider">
                    Description
                  </th>
                  <th className="px-5 py-3.5 text-xs font-bold text-warm-500 uppercase tracking-wider text-right">
                    Details
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {logs.map((log) => {
                  const isExpanded = expandedLogId === log.id;
                  const logDate = new Date(log.occurred_at);
                  const hasDetails = log.details.length > 0 || log.changes.length > 0;

                  return (
                    <React.Fragment key={log.id}>
                      <tr
                        className={`hover:bg-warm-50/60 transition-colors border-l-2 ${
                          log.action === "deleted"
                            ? "border-l-red-400"
                            : log.action === "created"
                            ? "border-l-emerald-500"
                            : log.action === "updated"
                            ? "border-l-sky-400"
                            : "border-l-transparent"
                        }`}
                      >
                        {/* When */}
                        <td className="px-5 py-3.5 whitespace-nowrap">
                          <div className="text-sm font-semibold text-warm-800">
                            {logDate.toLocaleDateString("en-US", {
                              year: "numeric",
                              month: "short",
                              day: "numeric",
                            })}
                          </div>
                          <div className="text-xs text-warm-400 font-mono mt-0.5">
                            {logDate.toLocaleTimeString("en-US", {
                              hour: "2-digit",
                              minute: "2-digit",
                              second: "2-digit",
                            })}
                          </div>
                        </td>

                        {/* Event */}
                        <td className="px-5 py-3.5 whitespace-nowrap">
                          <Badge tone={eventTones[log.action] ?? "zinc"}>
                            {log.action_label}
                          </Badge>
                          <div className="flex items-center gap-1.5 mt-1">
                            <span className="text-xs text-warm-400 uppercase">
                              {log.category} · {log.domain}
                            </span>
                            <Badge tone={outcomeTones[log.outcome] ?? "zinc"}>{log.outcome}</Badge>
                          </div>
                        </td>

                        {/* Actor */}
                        <td className="px-5 py-3.5">
                          {log.actor ? (
                            <>
                              <div className="flex items-center gap-1.5">
                                <span className="text-sm font-semibold text-warm-800">
                                  {log.actor.name}
                                </span>
                                {log.actor.role && (
                                  <Badge tone={roleTones[log.actor.role] ?? "zinc"}>
                                    {log.actor.role}
                                  </Badge>
                                )}
                              </div>
                              <div className="text-xs text-warm-400 font-mono mt-0.5">
                                {log.actor.kind}
                              </div>
                            </>
                          ) : (
                            <span className="text-sm text-warm-400 italic">System</span>
                          )}
                        </td>

                        {/* Subject */}
                        <td className="px-5 py-3.5 whitespace-nowrap">
                          {log.subject ? (
                            <>
                              <div className="text-sm font-semibold text-warm-700 font-mono">
                                {log.subject.label}
                              </div>
                              {log.subject.id !== null && (
                                <div className="text-xs text-warm-400 font-mono mt-0.5">
                                  {log.subject.id}
                                </div>
                              )}
                            </>
                          ) : (
                            <span className="text-warm-300 text-sm">-</span>
                          )}
                        </td>

                        {/* Description */}
                        <td className="px-5 py-3.5 max-w-xs">
                          <div className="text-sm text-warm-600 line-clamp-2">
                            {log.summary || <span className="text-warm-300">-</span>}
                          </div>
                        </td>

                        {/* Properties toggle */}
                        <td className="px-5 py-3.5 text-right whitespace-nowrap">
                          <button
                            onClick={() => toggleRow(log.id)}
                            disabled={!hasDetails}
                            aria-expanded={isExpanded}
                            className={`inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-xs font-bold uppercase tracking-wider transition-colors select-none ${
                              hasDetails
                                ? "border-warm-200 bg-warm-50 text-warm-500 hover:text-warm-800 hover:border-warm-300 hover:bg-white cursor-pointer"
                                : "border-transparent bg-transparent text-warm-300 cursor-not-allowed"
                            }`}
                          >
                            {isExpanded ? (
                              <>
                                <ChevronUp className="h-3 w-3" />
                                Hide
                              </>
                            ) : (
                              <>
                                <ChevronDown className="h-3 w-3" />
                                Show
                              </>
                            )}
                          </button>
                        </td>
                      </tr>

                      {/* Expanded structured details */}
                      {isExpanded && hasDetails && (
                        <tr className="bg-warm-50/80">
                          <td colSpan={6} className="px-6 py-4">
                            <div className="space-y-4">
                              <div className="text-xs font-bold text-warm-500 uppercase tracking-wider">
                                Event details
                                <span className="ml-2 font-normal normal-case text-warm-400">
                                  Safe, structured audit metadata only
                                </span>
                              </div>

                              {log.details.length > 0 && (
                                <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                  {log.details.map((detail) => (
                                    <div key={detail.key} className="rounded-xl border border-warm-200 bg-white px-3 py-2">
                                      <dt className="text-xs font-bold uppercase tracking-wider text-warm-400">
                                        {detail.label}
                                      </dt>
                                      <dd className="mt-1 text-sm font-semibold text-warm-700 break-words">
                                        {formatDetailValue(detail.value)}
                                      </dd>
                                    </div>
                                  ))}
                                </dl>
                              )}

                              {log.changes.length > 0 && (
                                <div className="overflow-x-auto rounded-xl border border-warm-200 bg-white">
                                  <table className="w-full min-w-[560px] text-sm">
                                    <thead className="bg-warm-50 text-xs uppercase tracking-wider text-warm-500">
                                      <tr>
                                        <th className="px-3 py-2 text-left">Field</th>
                                        <th className="px-3 py-2 text-left">Before</th>
                                        <th className="px-3 py-2 text-left">After</th>
                                      </tr>
                                    </thead>
                                    <tbody className="divide-y divide-warm-100">
                                      {log.changes.map((change) => (
                                        <tr key={change.field}>
                                          <td className="px-3 py-2 font-semibold text-warm-700">{change.label}</td>
                                          <td className="px-3 py-2 text-warm-500">
                                            {formatChangeValue(change.old_value, change.redacted)}
                                          </td>
                                          <td className="px-3 py-2 text-warm-500">
                                            {formatChangeValue(change.new_value, change.redacted)}
                                          </td>
                                        </tr>
                                      ))}
                                    </tbody>
                                  </table>
                                </div>
                              )}
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>

          <Pagination meta={meta} page={page} onPageChange={setPage} />
        </div>
      )}
    </div>
  );
}
