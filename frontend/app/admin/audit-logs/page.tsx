"use client";

import React, { useEffect, useState, useMemo } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { listAuditLogs, AuditLog, ListAuditLogsParams } from "@/services/auditLogService";
import { listUsers } from "@/services/adminUserService";
import { User } from "@/services/authService";
import { PaginationMeta } from "@/services/inventoryService";
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

export default function AuditLogsPage() {
  const { user: _currentUser } = useAuth();
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
  const [causerId, setCauserId] = useState<string>("All");
  const [subjectType, setSubjectType] = useState<string>("All");
  const [eventFilter, setEventFilter] = useState<string>("All");
  const [startDate, setStartDate] = useState<string>("");
  const [endDate, setEndDate] = useState<string>("");

  // Expandable row state
  const [expandedLogId, setExpandedLogId] = useState<number | null>(null);

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
      if (causerId !== "All") params.causer_id = parseInt(causerId);
      if (subjectType !== "All") params.subject_type = subjectType;
      if (eventFilter !== "All") params.event = eventFilter;
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
  }, [page, causerId, subjectType, eventFilter, startDate, endDate]);

  function handleResetFilters() {
    setCauserId("All");
    setSubjectType("All");
    setEventFilter("All");
    setStartDate("");
    setEndDate("");
    setPage(1);
  }

  const uniqueSubjectTypes = useMemo(() => [
    { label: "Patient", value: "App\\Models\\Patient" },
    { label: "User Account", value: "App\\Models\\User" },
    { label: "Budget", value: "App\\Models\\Budget" },
    { label: "Budget Ledger", value: "App\\Models\\BudgetLedger" },
    { label: "Purchase Order", value: "App\\Models\\PurchaseOrder" },
    { label: "NCP Assessment", value: "App\\Models\\Assessment" },
    { label: "NCP Diagnosis", value: "App\\Models\\Diagnosis" },
    { label: "NCP Intervention", value: "App\\Models\\Intervention" },
    { label: "NCP Monitoring", value: "App\\Models\\Monitoring" },
    { label: "Menu Cycle", value: "App\\Models\\MenuCycle" },
    { label: "Meal Prep Log", value: "App\\Models\\MealPrepLog" },
    { label: "FsItem", value: "App\\Models\\FsItem" },
  ], []);

  function toggleRow(logId: number) {
    setExpandedLogId((prev) => (prev === logId ? null : logId));
  }

  // shared input classes matching admin/users pattern
  const selCls =
    "w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 text-zinc-800";

  return (
    <div className="space-y-6 font-sans">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
            <span>Admin</span>
            <span>/</span>
            <span className="text-zinc-600 font-bold">Audit Logs</span>
          </div>
          <h1 className="text-xl font-extrabold text-zinc-900 tracking-tight mt-1 flex items-center gap-2">
            <Shield className="h-5 w-5 text-emerald-600" />
            System Audit Log
          </h1>
          <p className="text-xs text-zinc-500 mt-0.5">
            Paginated ledger of all create, update, delete, and login events across the system.
          </p>
        </div>

        <button
          onClick={() => void loadLogs()}
          disabled={loading}
          className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 bg-white text-xs font-semibold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition-colors disabled:opacity-50 select-none cursor-pointer shrink-0"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} />
          Refresh
        </button>
      </div>

      {/* Filter bar */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-sm">
        <div className="flex items-center gap-1.5 text-xs font-bold text-zinc-500 uppercase tracking-wider mb-3 select-none">
          <Filter className="h-3.5 w-3.5" />
          Filters
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
          {/* Actor */}
          <div>
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">
              Actor
            </label>
            <select
              value={causerId}
              onChange={(e) => { setCauserId(e.target.value); setPage(1); }}
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

          {/* Subject type */}
          <div>
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">
              Subject type
            </label>
            <select
              value={subjectType}
              onChange={(e) => { setSubjectType(e.target.value); setPage(1); }}
              className={selCls}
            >
              <option value="All">All subjects</option>
              {uniqueSubjectTypes.map((t) => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </select>
          </div>

          {/* Event */}
          <div>
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">
              Event
            </label>
            <select
              value={eventFilter}
              onChange={(e) => { setEventFilter(e.target.value); setPage(1); }}
              className={selCls}
            >
              <option value="All">All events</option>
              <option value="created">Created</option>
              <option value="updated">Updated</option>
              <option value="deleted">Deleted</option>
              <option value="login">Login</option>
              <option value="logout">Logout</option>
              <option value="login_failed">Login Failed</option>
              <option value="password_changed">Password Changed</option>
              <option value="password_reset">Password Reset</option>
            </select>
          </div>

          {/* Start date */}
          <div>
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">
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
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">
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

        <div className="flex justify-between items-center mt-3 pt-3 border-t border-zinc-100 select-none">
          <span className="text-[10px] text-zinc-400 font-medium">
            {meta.total} total {meta.total === 1 ? "entry" : "entries"} &mdash; page {meta.current_page} of {meta.last_page}
          </span>
          <button
            onClick={handleResetFilters}
            className="text-[10px] text-zinc-400 hover:text-zinc-700 font-semibold uppercase tracking-wider transition-colors cursor-pointer"
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
            <div className="text-xs text-red-700 font-bold">Failed to load audit logs</div>
            <div className="text-xs text-red-600 mt-0.5">{error}</div>
            <button
              onClick={() => void loadLogs()}
              className="mt-2 text-xs text-red-700 underline hover:no-underline cursor-pointer"
            >
              Retry
            </button>
          </div>
        </div>
      ) : loading ? (
        <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center flex flex-col items-center justify-center gap-3 shadow-sm">
          <RefreshCw className="h-6 w-6 text-emerald-600 animate-spin" />
          <div className="text-xs text-zinc-500 font-semibold uppercase tracking-wider">
            Loading audit logs...
          </div>
        </div>
      ) : logs.length === 0 ? (
        <div className="bg-white border border-zinc-200 rounded-2xl p-16 text-center shadow-sm">
          <div className="p-3 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400 mb-4">
            <Activity className="h-8 w-8" />
          </div>
          <h3 className="text-sm font-bold text-zinc-700">No audit entries found</h3>
          <p className="text-xs text-zinc-400 mt-1 max-w-sm mx-auto">
            No entries match the active filter configuration. Try adjusting or clearing filters.
          </p>
        </div>
      ) : (
        <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left min-w-[900px]">
              <thead className="bg-zinc-50 border-b border-zinc-100">
                <tr>
                  <th className="px-5 py-3.5 text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    When
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    Event
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    Actor
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    Subject
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    Description
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-bold text-zinc-500 uppercase tracking-wider text-right">
                    Properties
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {logs.map((log) => {
                  const isExpanded = expandedLogId === log.id;
                  const logDate = new Date(log.created_at);
                  const hasProps =
                    !!log.properties && Object.keys(log.properties).length > 0;

                  return (
                    <React.Fragment key={log.id}>
                      <tr
                        className={`hover:bg-zinc-50/60 transition-colors border-l-2 ${
                          log.event === "deleted"
                            ? "border-l-red-400"
                            : log.event === "created"
                            ? "border-l-emerald-500"
                            : log.event === "updated"
                            ? "border-l-sky-400"
                            : "border-l-transparent"
                        }`}
                      >
                        {/* When */}
                        <td className="px-5 py-3.5 whitespace-nowrap">
                          <div className="text-xs font-semibold text-zinc-800">
                            {logDate.toLocaleDateString("en-US", {
                              year: "numeric",
                              month: "short",
                              day: "numeric",
                            })}
                          </div>
                          <div className="text-[10px] text-zinc-400 font-mono mt-0.5">
                            {logDate.toLocaleTimeString("en-US", {
                              hour: "2-digit",
                              minute: "2-digit",
                              second: "2-digit",
                            })}
                          </div>
                        </td>

                        {/* Event */}
                        <td className="px-5 py-3.5 whitespace-nowrap">
                          {log.event ? (
                            <Badge tone={eventTones[log.event] ?? "zinc"}>
                              {log.event}
                            </Badge>
                          ) : (
                            <span className="text-zinc-300 text-xs">-</span>
                          )}
                          {log.log_name && (
                            <div className="text-[9px] text-zinc-400 font-mono mt-1 uppercase">
                              {log.log_name}
                            </div>
                          )}
                        </td>

                        {/* Actor */}
                        <td className="px-5 py-3.5">
                          {log.causer ? (
                            <>
                              <div className="flex items-center gap-1.5">
                                <span className="text-xs font-semibold text-zinc-800">
                                  {log.causer.name}
                                </span>
                                <Badge tone={roleTones[log.causer.role] ?? "zinc"}>
                                  {log.causer.role}
                                </Badge>
                              </div>
                              <div className="text-[10px] text-zinc-400 font-mono mt-0.5">
                                {log.causer.email}
                              </div>
                            </>
                          ) : (
                            <span className="text-xs text-zinc-400 italic">System</span>
                          )}
                        </td>

                        {/* Subject */}
                        <td className="px-5 py-3.5 whitespace-nowrap">
                          {log.subject_type ? (
                            <>
                              <div className="text-xs font-semibold text-zinc-700 font-mono">
                                {log.subject_type.split("\\").pop()}
                              </div>
                              {log.subject_id !== null && (
                                <div className="text-[10px] text-zinc-400 font-mono mt-0.5">
                                  #{log.subject_id}
                                </div>
                              )}
                            </>
                          ) : (
                            <span className="text-zinc-300 text-xs">-</span>
                          )}
                        </td>

                        {/* Description */}
                        <td className="px-5 py-3.5 max-w-xs">
                          <div className="text-xs text-zinc-600 line-clamp-2">
                            {log.description || <span className="text-zinc-300">-</span>}
                          </div>
                        </td>

                        {/* Properties toggle */}
                        <td className="px-5 py-3.5 text-right whitespace-nowrap">
                          <button
                            onClick={() => toggleRow(log.id)}
                            disabled={!hasProps}
                            aria-expanded={isExpanded}
                            className={`inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-[10px] font-bold uppercase tracking-wider transition-colors select-none ${
                              hasProps
                                ? "border-zinc-200 bg-zinc-50 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300 hover:bg-white cursor-pointer"
                                : "border-transparent bg-transparent text-zinc-300 cursor-not-allowed"
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

                      {/* Expanded properties panel */}
                      {isExpanded && log.properties && (
                        <tr className="bg-zinc-50/80">
                          <td colSpan={6} className="px-6 py-4">
                            <div className="space-y-2">
                              <div className="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                                Properties
                                <span className="ml-2 font-normal normal-case text-zinc-400">
                                  (redacted at write-time - no raw PHI stored)
                                </span>
                              </div>

                              {/* Structured old/new diff if present */}
                              {Boolean(log.properties.old !== undefined ||
                                log.properties.attributes !== undefined) ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                  {log.properties.old !== undefined && (
                                    <div>
                                      <div className="text-[10px] font-bold text-red-600 uppercase tracking-wider mb-1">
                                        Before (old)
                                      </div>
                                      <pre className="text-[10px] bg-white border border-zinc-200 rounded-xl p-3 font-mono text-zinc-600 overflow-x-auto max-h-48 leading-5">
                                        {JSON.stringify(log.properties.old, null, 2)}
                                      </pre>
                                    </div>
                                  )}
                                  {log.properties.attributes !== undefined && (
                                    <div>
                                      <div className="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1">
                                        After (new)
                                      </div>
                                      <pre className="text-[10px] bg-white border border-zinc-200 rounded-xl p-3 font-mono text-zinc-600 overflow-x-auto max-h-48 leading-5">
                                        {JSON.stringify(log.properties.attributes, null, 2)}
                                      </pre>
                                    </div>
                                  )}
                                </div>
                              ) : null}

                              {/* Request metadata (url / method / ip) */}
                              {Boolean(log.properties.url ||
                                log.properties.method ||
                                log.properties.ip) && (
                                <div className="flex flex-wrap gap-3 text-[10px] font-mono text-zinc-500 mt-1">
                                  {Boolean(log.properties.method) && (
                                    <span className="px-2 py-0.5 rounded bg-white border border-zinc-200">
                                      {String(log.properties.method)}
                                    </span>
                                  )}
                                  {Boolean(log.properties.url) && (
                                    <span className="px-2 py-0.5 rounded bg-white border border-zinc-200 truncate max-w-xs">
                                      {String(log.properties.url)}
                                    </span>
                                  )}
                                  {Boolean(log.properties.ip) && (
                                    <span className="px-2 py-0.5 rounded bg-white border border-zinc-200">
                                      IP: {String(log.properties.ip)}
                                    </span>
                                  )}
                                </div>
                              )}

                              {/* Fallback: full JSON for any other structure */}
                              {log.properties.old === undefined &&
                                log.properties.attributes === undefined && (
                                  <pre className="text-[10px] bg-white border border-zinc-200 rounded-xl p-3 font-mono text-zinc-600 overflow-x-auto max-h-48 leading-5">
                                    {JSON.stringify(log.properties, null, 2)}
                                  </pre>
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
