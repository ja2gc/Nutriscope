"use client";

import React, { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { fetchDashboard, DashboardData } from "@/services/adminDashboardService";
import { listAuditLogs, AuditLog } from "@/services/auditLogService";
import {
  Users,
  Shield,
  Cpu,
  FileText,
  Activity,
  ArrowRight,
  RefreshCw,
  AlertCircle,
  Database,
  Megaphone,
} from "lucide-react";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from "recharts";

function formatNumber(num: number) {
  return num.toLocaleString();
}

function formatTokens(num: number) {
  if (num >= 1_000_000) {
    return `${(num / 1_000_000).toFixed(2)}M`;
  }
  if (num >= 1_000) {
    return `${(num / 1_000).toFixed(1)}k`;
  }
  return num.toString();
}

export default function AdminDashboardPage() {
  const { user } = useAuth();
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [recentLogs, setRecentLogs] = useState<AuditLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  async function loadData(isRefresh = false) {
    try {
      if (isRefresh) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }
      setError(null);
      
      const [data, logsResponse] = await Promise.all([
        fetchDashboard(),
        listAuditLogs({ per_page: 5 }),
      ]);
      
      setDashboardData(data);
      setRecentLogs(logsResponse.data);
    } catch (err: any) {
      setError(err.message || "Failed to load admin dashboard data.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }

  useEffect(() => {
    void loadData();
  }, []);

  const chartData = useMemo(() => {
    if (!dashboardData?.ai_usage?.by_endpoint) return [];
    return Object.entries(dashboardData.ai_usage.by_endpoint).map(([endpoint, stats]) => {
      // Clean up endpoint name for clean labels
      const name = endpoint
        .replace(/^\/api\/(v1\/)?/, "")
        .replace(/\/+/g, " ");
      return {
        name: name.charAt(0).toUpperCase() + name.slice(1),
        tokens: stats.tokens,
        calls: stats.calls,
      };
    });
  }, [dashboardData]);

  if (loading) {
    return (
      <div className="space-y-6 animate-pulse">
        <div className="h-8 w-48 bg-zinc-900 rounded-lg" />
        <div className="h-4 w-96 bg-zinc-900 rounded-lg" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-24 bg-zinc-900 border border-zinc-800 rounded-2xl" />
          ))}
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
          <div className="lg:col-span-2 h-96 bg-zinc-900 border border-zinc-800 rounded-3xl" />
          <div className="h-96 bg-zinc-900 border border-zinc-800 rounded-3xl" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 font-sans text-zinc-100">
      {/* Breadcrumb & Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 select-none">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-zinc-500">
            <span>Admin</span>
            <span className="text-zinc-700">/</span>
            <span className="text-zinc-400 font-bold">Dashboard</span>
          </div>
          <h1 className="text-xl font-extrabold text-white tracking-tight mt-1">
            Admin Console
          </h1>
          <p className="text-xs text-zinc-400 mt-0.5">
            System configuration, active directories, AI consumption metrics, and logs.
          </p>
        </div>
        <button
          onClick={() => void loadData(true)}
          disabled={refreshing}
          className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-bold uppercase tracking-wider text-zinc-300 hover:text-white hover:bg-zinc-850 active:bg-zinc-800 transition-colors disabled:opacity-50"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${refreshing ? "animate-spin" : ""}`} />
          {refreshing ? "Refreshing..." : "Refresh"}
        </button>
      </div>

      {error && (
        <div className="bg-red-950/30 border border-red-900/50 p-4 rounded-xl flex items-start gap-3">
          <AlertCircle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <div className="text-xs text-red-400 font-bold">Error loading metrics</div>
            <div className="text-xs text-red-500/80 mt-0.5">{error}</div>
          </div>
        </div>
      )}

      {/* KPI Cards Grid */}
      {dashboardData && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Active Users */}
          <div className="bg-zinc-900 border border-zinc-800 rounded-2xl p-4 flex items-center justify-between shadow-md hover:border-zinc-700 transition-all">
            <div className="space-y-1">
              <span className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider block">
                Total Active Users
              </span>
              <span className="text-2xl font-extrabold text-white block tabular-nums">
                {formatNumber(dashboardData.users.total)}
              </span>
              <span className="text-[10px] text-zinc-400 block font-medium">
                Admin: {dashboardData.users.by_role.Admin} · RND: {dashboardData.users.by_role.RND} · FSS: {dashboardData.users.by_role.FSS}
              </span>
            </div>
            <div className="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
              <Users className="h-5 w-5" />
            </div>
          </div>

          {/* Patients */}
          <div className="bg-zinc-900 border border-zinc-800 rounded-2xl p-4 flex items-center justify-between shadow-md hover:border-zinc-700 transition-all">
            <div className="space-y-1">
              <span className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider block">
                Patients in Care
              </span>
              <span className="text-2xl font-extrabold text-white block tabular-nums">
                {formatNumber(dashboardData.patients.total)}
              </span>
              <span className="text-[10px] text-zinc-400 block font-medium">
                Clinical records tracked
              </span>
            </div>
            <div className="p-2.5 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20">
              <Database className="h-5 w-5" />
            </div>
          </div>

          {/* AI Usage */}
          <div className="bg-zinc-900 border border-zinc-800 rounded-2xl p-4 flex items-center justify-between shadow-md hover:border-zinc-700 transition-all">
            <div className="space-y-1">
              <span className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider block">
                AI API Operations
              </span>
              <span className="text-2xl font-extrabold text-white block tabular-nums">
                {formatNumber(dashboardData.ai_usage.total_calls)}
              </span>
              <span className="text-[10px] text-zinc-400 block font-medium">
                Tokens: {formatTokens(dashboardData.ai_usage.total_tokens)}
              </span>
            </div>
            <div className="p-2.5 rounded-xl bg-purple-500/10 text-purple-400 border border-purple-500/20">
              <Cpu className="h-5 w-5" />
            </div>
          </div>

          {/* Audit Logs */}
          <div className="bg-zinc-900 border border-zinc-800 rounded-2xl p-4 flex items-center justify-between shadow-md hover:border-zinc-700 transition-all">
            <div className="space-y-1">
              <span className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider block">
                Security Audit Log
              </span>
              <span className="text-2xl font-extrabold text-white block tabular-nums">
                {formatNumber(dashboardData.audit_logs.total)}
              </span>
              <span className="text-[10px] text-emerald-400 block font-medium">
                +{dashboardData.audit_logs.last_7_days} in last 7 days
              </span>
            </div>
            <div className="p-2.5 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
              <Shield className="h-5 w-5" />
            </div>
          </div>
        </div>
      )}

      {/* Main Grid Content */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Side: AI usage chart & detailed stats */}
        <div className="lg:col-span-2 space-y-6">
          {/* Chart Container */}
          <div className="bg-zinc-900 border border-zinc-800 rounded-3xl overflow-hidden shadow-sm">
            <div className="px-5 py-4 border-b border-zinc-800 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-xs font-bold text-white uppercase tracking-[0.18em]">
                  AI Token Consumption
                </h3>
                <p className="text-[10px] text-zinc-400 mt-1">
                  Aggregated token counts processed by internal AI endpoints.
                </p>
              </div>
              <span className="inline-flex px-2 py-0.5 rounded-full border border-purple-500/20 bg-purple-500/10 text-purple-400 text-[10px] font-bold uppercase tracking-wider select-none">
                Live Stats
              </span>
            </div>

            <div className="p-6">
              {chartData.length === 0 ? (
                <div className="h-72 flex flex-col items-center justify-center text-center">
                  <div className="p-3 bg-zinc-950 border border-zinc-850 rounded-2xl text-zinc-600">
                    <Cpu className="h-8 w-8" />
                  </div>
                  <h3 className="text-xs font-bold text-zinc-400 mt-3">No AI API logs recorded</h3>
                  <p className="text-[10px] text-zinc-500 mt-1 max-w-xs leading-relaxed">
                    AI generation tokens will map here once RND clinicians trigger automated NCP processes.
                  </p>
                </div>
              ) : (
                <div className="h-72 w-full">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                      data={chartData}
                      layout="vertical"
                      margin={{ top: 5, right: 30, left: 10, bottom: 5 }}
                    >
                      <CartesianGrid strokeDasharray="3 3" stroke="#1f1f22" horizontal={false} />
                      <XAxis
                        type="number"
                        stroke="#71717a"
                        fontSize={10}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={formatTokens}
                      />
                      <YAxis
                        dataKey="name"
                        type="category"
                        stroke="#71717a"
                        fontSize={10}
                        width={120}
                        tickLine={false}
                        axisLine={false}
                      />
                      <Tooltip
                        cursor={{ fill: "rgba(255, 255, 255, 0.03)" }}
                        contentStyle={{
                          backgroundColor: "#09090b",
                          borderColor: "#27272a",
                          borderRadius: "12px",
                          color: "#f4f4f5",
                          fontSize: "11px",
                        }}
                      />
                      <Bar dataKey="tokens" name="Total Tokens" fill="#10b981" radius={[0, 4, 4, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              )}
            </div>
          </div>

          {/* Quick Shortcuts */}
          <div className="bg-zinc-900 border border-zinc-800 rounded-3xl p-5 shadow-sm">
            <h3 className="text-xs font-bold text-white uppercase tracking-[0.18em] mb-4">
              Quick Actions
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Link
                href="/admin/users"
                className="group border border-zinc-800 hover:border-zinc-700 bg-zinc-950 p-4 rounded-2xl flex flex-col justify-between h-28 hover:shadow-md transition-all"
              >
                <div className="p-1.5 rounded-lg bg-zinc-900 text-zinc-400 group-hover:text-emerald-400 group-hover:bg-emerald-500/10 w-fit transition-colors">
                  <Users className="h-4 w-4" />
                </div>
                <div>
                  <div className="text-xs font-bold text-white flex items-center gap-1 group-hover:text-emerald-400 transition-colors">
                    Manage Accounts
                    <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                  </div>
                  <div className="text-[10px] text-zinc-500 mt-0.5">RBAC & credentials setup</div>
                </div>
              </Link>

              <Link
                href="/admin/audit-logs"
                className="group border border-zinc-800 hover:border-zinc-700 bg-zinc-950 p-4 rounded-2xl flex flex-col justify-between h-28 hover:shadow-md transition-all"
              >
                <div className="p-1.5 rounded-lg bg-zinc-900 text-zinc-400 group-hover:text-amber-400 group-hover:bg-amber-500/10 w-fit transition-colors">
                  <Activity className="h-4 w-4" />
                </div>
                <div>
                  <div className="text-xs font-bold text-white flex items-center gap-1 group-hover:text-amber-400 transition-colors">
                    Audit Log Browser
                    <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                  </div>
                  <div className="text-[10px] text-zinc-500 mt-0.5">Filter & monitor operational actions</div>
                </div>
              </Link>

              <Link
                href="/admin/announcements"
                className="group border border-zinc-800 hover:border-zinc-700 bg-zinc-950 p-4 rounded-2xl flex flex-col justify-between h-28 hover:shadow-md transition-all"
              >
                <div className="p-1.5 rounded-lg bg-zinc-900 text-zinc-400 group-hover:text-blue-400 group-hover:bg-blue-500/10 w-fit transition-colors">
                  <Megaphone className="h-4 w-4" />
                </div>
                <div>
                  <div className="text-xs font-bold text-white flex items-center gap-1 group-hover:text-blue-400 transition-colors">
                    Publish Feed
                    <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                  </div>
                  <div className="text-[10px] text-zinc-500 mt-0.5">Broadcast system updates</div>
                </div>
              </Link>
            </div>
          </div>
        </div>

        {/* Right Side: Recent activity logs feed */}
        <div className="space-y-6">
          <div className="bg-zinc-900 border border-zinc-800 rounded-3xl overflow-hidden shadow-sm flex flex-col h-full">
            <div className="px-5 py-4 border-b border-zinc-800 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-xs font-bold text-white uppercase tracking-[0.18em]">
                  Security Audit Feed
                </h3>
                <p className="text-[10px] text-zinc-400 mt-1">
                  Recent activities logged on the server.
                </p>
              </div>
              <Link
                href="/admin/audit-logs"
                className="text-[10px] font-bold text-zinc-400 hover:text-white transition-colors"
              >
                View All
              </Link>
            </div>

            <div className="p-5 flex-1 divide-y divide-zinc-800/60">
              {recentLogs.length === 0 ? (
                <div className="py-12 text-center">
                  <div className="p-2.5 bg-zinc-950 border border-zinc-850 rounded-xl w-fit mx-auto text-zinc-600">
                    <Activity className="h-5 w-5" />
                  </div>
                  <h4 className="text-xs font-bold text-zinc-400 mt-3">No system activity</h4>
                  <p className="text-[10px] text-zinc-500 mt-1">
                    System operations will populate here live.
                  </p>
                </div>
              ) : (
                recentLogs.map((log) => {
                  const initials = log.causer?.name
                    ? log.causer.name
                        .split(" ")
                        .filter(Boolean)
                        .slice(0, 2)
                        .map((n) => n[0]?.toUpperCase())
                        .join("")
                    : "SYS";
                  
                  return (
                    <div key={log.id} className="py-3.5 first:pt-0 last:pb-0 flex gap-3 text-xs leading-relaxed">
                      <div className="h-8 w-8 rounded-full bg-zinc-950 border border-zinc-800 text-zinc-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                        {initials}
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="text-zinc-200">
                          <span className="font-semibold text-white">{log.causer?.name || "System"}</span>{" "}
                          <span className="text-zinc-400">{log.description}</span>
                        </div>
                        <div className="flex items-center gap-2 mt-1 text-[9px] font-bold uppercase tracking-wider text-zinc-500">
                          <span className="text-zinc-600">{log.log_name}</span>
                          <span>·</span>
                          <span>
                            {new Date(log.created_at).toLocaleTimeString([], {
                              hour: "2-digit",
                              minute: "2-digit",
                            })}
                          </span>
                        </div>
                      </div>
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
