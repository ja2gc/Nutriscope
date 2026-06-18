"use client";

import React, { useEffect, useState, useMemo } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { listAuditLogs, AuditLog, ListAuditLogsParams } from "@/services/auditLogService";
import { listUsers } from "@/services/adminUserService";
import { User } from "@/services/authService";
import { PaginationMeta } from "@/services/inventoryService";
import {
  Activity,
  Search,
  Filter,
  Calendar,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  AlertTriangle,
  ChevronDown,
  ChevronUp,
  Shield,
  Eye,
  EyeOff,
} from "lucide-react";
import { Badge, BadgeTone } from "@/components/ui/Badge";

const eventTones: Record<string, BadgeTone> = {
  created: "emerald",
  updated: "sky",
  deleted: "red",
  login: "violet",
  logout: "zinc",
};

export default function AuditLogsPage() {
  const { user: currentUser } = useAuth();
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [meta, setMeta] = useState<PaginationMeta>({
    current_page: 1,
    per_page: 25,
    total: 0,
    last_page: 1,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
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
      // Best-effort for actor list dropdown
    }
  }

  async function loadLogs() {
    try {
      setLoading(true);
      setError(null);

      const params: ListAuditLogsParams = {
        page,
        per_page: perPage,
      };

      if (causerId !== "All") params.causer_id = parseInt(causerId);
      if (subjectType !== "All") params.subject_type = subjectType;
      if (eventFilter !== "All") params.event = eventFilter;
      if (startDate) params.start = startDate;
      if (endDate) params.end = endDate;

      const response = await listAuditLogs(params);
      setLogs(response.data);
      setMeta(response.meta);
    } catch (err: any) {
      setError(err.message || "Failed to fetch audit log records.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadDirectoryData();
  }, []);

  useEffect(() => {
    void loadLogs();
  }, [page, perPage, causerId, subjectType, eventFilter, startDate, endDate]);

  function handleResetFilters() {
    setCauserId("All");
    setSubjectType("All");
    setEventFilter("All");
    setStartDate("");
    setEndDate("");
    setPage(1);
  }

  const uniqueSubjectTypes = useMemo(() => {
    // Collect some common known model types for easier filtering
    return [
      { label: "Patient", value: "Patient" },
      { label: "User Account", value: "User" },
      { label: "NCP Assessment", value: "Assessment" },
      { label: "NCP Diagnosis", value: "Diagnosis" },
      { label: "NCP Intervention", value: "Intervention" },
      { label: "NCP Monitoring", value: "Monitoring" },
      { label: "Menu Cycle", value: "MenuCycle" },
      { label: "Purchase Order", value: "PurchaseOrder" },
      { label: "Budget", value: "Budget" },
      { label: "Meal Prep Log", value: "MealPrepLog" },
      { label: "FsItem", value: "FsItem" },
    ];
  }, []);

  function toggleRow(logId: number) {
    if (expandedLogId === logId) {
      setExpandedLogId(null);
    } else {
      setExpandedLogId(logId);
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
            <span className="text-zinc-400 font-bold">System Audit</span>
          </div>
          <h1 className="text-xl font-extrabold text-white tracking-tight mt-1 flex items-center gap-2">
            <Shield className="h-5 w-5 text-amber-500" />
            Security & System Audit logs
          </h1>
          <p className="text-xs text-zinc-400 mt-0.5">
            Cryptographic ledger tracking administrative access, user logins, and database alterations.
          </p>
        </div>

        <button
          onClick={() => void loadLogs()}
          disabled={loading}
          className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-bold uppercase tracking-wider text-zinc-300 hover:text-white hover:bg-zinc-850 active:bg-zinc-800 transition-colors disabled:opacity-50 select-none cursor-pointer"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} />
          Reload logs
        </button>
      </div>

      {/* Filters Bar */}
      <div className="bg-zinc-900 border border-zinc-800 rounded-3xl p-5 shadow-sm space-y-4">
        <div className="flex items-center gap-1.5 text-xs font-bold text-zinc-300 uppercase tracking-wider select-none">
          <Filter className="h-4 w-4 text-purple-400" />
          Filter Parameters
        </div>
        
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
          {/* Actor Filter */}
          <div className="space-y-1">
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider select-none">
              Actor (Causer)
            </label>
            <select
              value={causerId}
              onChange={(e) => {
                setCauserId(e.target.value);
                setPage(1);
              }}
              className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
            >
              <option value="All">All Actors</option>
              {users.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name} ({u.role})
                </option>
              ))}
            </select>
          </div>

          {/* Subject Filter */}
          <div className="space-y-1">
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider select-none">
              Subject Type
            </label>
            <select
              value={subjectType}
              onChange={(e) => {
                setSubjectType(e.target.value);
                setPage(1);
              }}
              className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
            >
              <option value="All">All Subjects</option>
              {uniqueSubjectTypes.map((type) => (
                <option key={type.value} value={type.value}>
                  {type.label}
                </option>
              ))}
            </select>
          </div>

          {/* Event Filter */}
          <div className="space-y-1">
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider select-none">
              Action Event
            </label>
            <select
              value={eventFilter}
              onChange={(e) => {
                setEventFilter(e.target.value);
                setPage(1);
              }}
              className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
            >
              <option value="All">All Events</option>
              <option value="created">Created</option>
              <option value="updated">Updated</option>
              <option value="deleted">Deleted</option>
              <option value="login">Login</option>
              <option value="logout">Logout</option>
            </select>
          </div>

          {/* Date range start */}
          <div className="space-y-1">
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider select-none">
              Start Date
            </label>
            <div className="relative">
              <input
                type="date"
                value={startDate}
                onChange={(e) => {
                  setStartDate(e.target.value);
                  setPage(1);
                }}
                className="w-full px-3 py-1.5 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
              />
            </div>
          </div>

          {/* Date range end */}
          <div className="space-y-1">
            <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider select-none">
              End Date
            </label>
            <div className="relative">
              <input
                type="date"
                value={endDate}
                onChange={(e) => {
                  setEndDate(e.target.value);
                  setPage(1);
                }}
                className="w-full px-3 py-1.5 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
              />
            </div>
          </div>
        </div>

        <div className="flex justify-between items-center select-none pt-2 border-t border-zinc-850/60">
          <span className="text-[10px] text-zinc-400 font-medium">
            Showing up to {perPage} entries per page. Total: {meta.total} matches.
          </span>
          <button
            onClick={handleResetFilters}
            className="text-[10px] text-zinc-400 hover:text-white font-bold uppercase tracking-wider transition-colors cursor-pointer"
          >
            Clear filters
          </button>
        </div>
      </div>

      {/* Audit Logs Table */}
      {error ? (
        <div className="bg-red-950/30 border border-red-900/50 p-4 rounded-xl flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <div className="text-xs text-red-400 font-bold">Failed to load audit logs</div>
            <div className="text-xs text-red-500/80 mt-0.5">{error}</div>
          </div>
        </div>
      ) : loading ? (
        <div className="bg-zinc-900 border border-zinc-800 rounded-3xl p-16 text-center flex flex-col items-center justify-center gap-3">
          <RefreshCw className="h-6 w-6 text-zinc-500 animate-spin" />
          <div className="text-xs text-zinc-400 font-semibold uppercase tracking-wider">
            Loading system activity logs...
          </div>
        </div>
      ) : logs.length === 0 ? (
        <div className="bg-zinc-900 border border-zinc-800 rounded-3xl p-16 text-center max-w-xl mx-auto shadow-sm">
          <div className="p-3 bg-zinc-950 border border-zinc-850 rounded-2xl w-fit mx-auto text-zinc-600 mb-4">
            <Activity className="h-8 w-8" />
          </div>
          <h3 className="text-sm font-bold text-zinc-300">No activity logs found</h3>
          <p className="text-xs text-zinc-500 mt-1 max-w-sm mx-auto leading-relaxed">
            No entries correspond to the active filter configuration.
          </p>
        </div>
      ) : (
        <div className="bg-zinc-900 border border-zinc-800 rounded-3xl shadow-md overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[900px]">
              <thead>
                <tr className="bg-zinc-950 border-b border-zinc-850">
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                    Timestamp
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                    Ledger Context
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                    Actor
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                    Action / Description
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                    Subject Entity
                  </th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider text-right">
                    Payload
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-850 bg-zinc-900/40">
                {logs.map((log, idx) => {
                  const isExpanded = expandedLogId === log.id;
                  const logDate = new Date(log.created_at);
                  const formattedDate = logDate.toLocaleDateString("en-US", {
                    year: "numeric",
                    month: "short",
                    day: "numeric",
                  });
                  const formattedTime = logDate.toLocaleTimeString("en-US", {
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                  });

                  return (
                    <React.Fragment key={log.id}>
                      <tr
                        className={`${
                          idx % 2 === 0 ? "bg-zinc-900/20" : "bg-zinc-900/60"
                        } hover:bg-zinc-850/30 transition-colors border-l-2 ${
                          log.event === "deleted"
                            ? "border-l-red-500"
                            : log.event === "created"
                            ? "border-l-emerald-500"
                            : "border-l-transparent"
                        }`}
                      >
                        {/* Timestamp */}
                        <td className="px-5 py-3.5 whitespace-nowrap">
                          <div className="text-xs font-bold text-white">{formattedDate}</div>
                          <div className="text-[9px] text-zinc-500 font-mono mt-0.5">{formattedTime}</div>
                        </td>

                        {/* Ledger Context */}
                        <td className="px-5 py-3.5">
                          <span className="text-[10px] font-mono text-zinc-450 uppercase font-extrabold block">
                            {log.log_name}
                          </span>
                          {log.event && (
                            <Badge tone={eventTones[log.event] || "zinc"} className="mt-1">
                              {log.event}
                            </Badge>
                          )}
                        </td>

                        {/* Actor */}
                        <td className="px-5 py-3.5">
                          {log.causer ? (
                            <>
                              <div className="text-xs font-bold text-white">{log.causer.name}</div>
                              <div className="text-[9px] text-zinc-500 font-mono mt-0.5">
                                {log.causer.email} · {log.causer.role}
                              </div>
                            </>
                          ) : (
                            <div className="text-xs text-zinc-500 font-bold uppercase tracking-wider">
                              System Process
                            </div>
                          )}
                        </td>

                        {/* Description */}
                        <td className="px-5 py-3.5 max-w-xs">
                          <div className="text-xs text-zinc-200 line-clamp-2">{log.description}</div>
                        </td>

                        {/* Subject Entity */}
                        <td className="px-5 py-3.5 whitespace-nowrap">
                          {log.subject_type ? (
                            <>
                              <div className="text-xs font-semibold text-zinc-300 font-mono">
                                {log.subject_type.split("\\").pop()}
                              </div>
                              {log.subject_id && (
                                <div className="text-[9px] text-zinc-500 font-mono mt-0.5">
                                  ID: {log.subject_id}
                                </div>
                              )}
                            </>
                          ) : (
                            <span className="text-zinc-500 font-mono text-xs">—</span>
                          )}
                        </td>

                        {/* Payload Action */}
                        <td className="px-5 py-3.5 text-right whitespace-nowrap">
                          <button
                            onClick={() => toggleRow(log.id)}
                            disabled={!log.properties || Object.keys(log.properties).length === 0}
                            className={`inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-[10px] font-bold uppercase tracking-wider transition-colors select-none cursor-pointer ${
                              log.properties && Object.keys(log.properties).length > 0
                                ? "border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-white hover:border-zinc-700"
                                : "border-transparent bg-transparent text-zinc-700 cursor-not-allowed"
                            }`}
                          >
                            {isExpanded ? (
                              <>
                                <EyeOff className="h-3 w-3" />
                                Hide Details
                              </>
                            ) : (
                              <>
                                <Eye className="h-3 w-3" />
                                View Payload
                              </>
                            )}
                          </button>
                        </td>
                      </tr>

                      {/* Expanded Panel */}
                      {isExpanded && log.properties && (
                        <tr className="bg-zinc-950/40">
                          <td colSpan={6} className="px-6 py-4 border-b border-zinc-850">
                            <div className="space-y-2.5 animate-fadeIn">
                              <div className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider select-none">
                                Payload Properties (Write-time sanitized, PHI protected)
                              </div>
                              <pre className="text-[10px] bg-zinc-950 p-4 rounded-xl border border-zinc-850 font-mono text-zinc-300 overflow-x-auto max-h-60 leading-5">
                                {JSON.stringify(log.properties, null, 2)}
                              </pre>
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

          {/* Pagination Footer */}
          {meta.last_page > 1 && (
            <div className="px-5 py-4 bg-zinc-950 border-t border-zinc-850 flex items-center justify-between gap-4 select-none">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 text-[10px] font-bold uppercase tracking-wider text-zinc-450 hover:text-white hover:bg-zinc-850 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <ChevronLeft className="h-3.5 w-3.5" />
                Previous
              </button>

              <span className="text-xs font-semibold text-zinc-400">
                Page {meta.current_page} of {meta.last_page}
              </span>

              <button
                disabled={page >= meta.last_page}
                onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 text-[10px] font-bold uppercase tracking-wider text-zinc-450 hover:text-white hover:bg-zinc-850 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Next
                <ChevronRight className="h-3.5 w-3.5" />
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
