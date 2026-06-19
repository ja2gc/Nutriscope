"use client";

import React, { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { fetchDashboard, DashboardData } from "@/services/adminDashboardService";
import { listAuditLogs, AuditLog } from "@/services/auditLogService";
import { KpiCard } from "@/components/ui/KpiCard";
import { Badge, BadgeTone } from "@/components/ui/Badge";
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
  LayoutDashboard,
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

function relativeTime(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60_000);
  if (mins < 1) return "just now";
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.floor(hrs / 24);
  return `${days}d ago`;
}

const eventTones: Record<string, BadgeTone> = {
  created: "emerald",
  updated: "sky",
  deleted: "red",
  login: "violet",
  logout: "zinc",
};

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
      const name = endpoint
        .replace(/^\/api\/(v1\/)?/, "")
        .replace(/\/+/g, " ")
        .trim();
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
        <div className="h-8 w-48 bg-zinc-100 rounded-lg" />
        <div className="h-4 w-96 bg-zinc-100 rounded-lg" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-24 bg-zinc-100 border border-zinc-200 rounded-2xl" />
          ))}
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
          <div className="lg:col-span-2 h-96 bg-zinc-100 border border-zinc-200 rounded-3xl" />
          <div className="h-96 bg-zinc-100 border border-zinc-200 rounded-3xl" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 font-sans">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-200 pb-5">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
            <span>Admin</span>
            <span className="text-zinc-300">/</span>
            <span className="text-zinc-600 font-bold">Dashboard</span>
          </div>
          <h1 className="text-xl font-extrabold text-zinc-950 tracking-tight mt-1 flex items-center gap-2.5">
            <LayoutDashboard className="h-5 w-5 text-emerald-600" />
            Admin Console
          </h1>
          <p className="text-xs text-zinc-500 mt-0.5 select-none">
            System configuration, active directories, AI consumption metrics, and logs.
          </p>
        </div>
        <button
          onClick={() => void loadData(true)}
          disabled={refreshing}
          className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-200 bg-white text-xs font-bold uppercase tracking-wider text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 active:bg-zinc-100 transition-colors disabled:opacity-50 shadow-sm"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${refreshing ? "animate-spin" : ""}`} />
          {refreshing ? "Refreshing..." : "Refresh"}
        </button>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-100 p-4 rounded-xl flex items-start gap-3">
          <AlertCircle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <div className="text-xs text-red-700 font-bold">Error loading metrics</div>
            <div className="text-xs text-red-600/80 mt-0.5">{error}</div>
          </div>
        </div>
      )}

      {/* KPI Cards Grid */}
      {dashboardData && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard
            label="Total Users"
            value={formatNumber(dashboardData.users.total)}
            hint={`Admin: ${dashboardData.users.by_role.Admin} · RND: ${dashboardData.users.by_role.RND} · FSS: ${dashboardData.users.by_role.FSS}`}
            tone="emerald"
          />
          <KpiCard
            label="Patients in Care"
            value={formatNumber(dashboardData.patients.total)}
            hint="Clinical records tracked"
            tone="sky"
          />
          <KpiCard
            label="AI API Calls"
            value={formatNumber(dashboardData.ai_usage.total_calls)}
            hint={`Tokens: ${formatTokens(dashboardData.ai_usage.total_tokens)}`}
            tone="zinc"
          />
          <KpiCard
            label="Audit Events"
            value={formatNumber(dashboardData.audit_logs.total)}
            hint={`+${dashboardData.audit_logs.last_7_days} in last 7 days`}
            tone="amber"
          />
        </div>
      )}

      {/* Main Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: AI usage chart + Quick Actions */}
        <div className="lg:col-span-2 space-y-6">
          {/* Token-usage chart */}
          <div className="bg-white border border-zinc-200 rounded-3xl overflow-hidden shadow-sm">
            <div className="px-5 py-4 border-b border-zinc-100 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em]">
                  AI Token Consumption
                </h3>
                <p className="text-[10px] text-zinc-500 mt-1">
                  Aggregated token counts processed by internal AI endpoints.
                </p>
              </div>
              <Badge tone="zinc">Live Stats</Badge>
            </div>

            <div className="p-6">
              {chartData.length === 0 ? (
                <div className="h-72 flex flex-col items-center justify-center text-center">
                  <div className="p-3 bg-zinc-50 border border-zinc-200 rounded-2xl text-zinc-400">
                    <Cpu className="h-8 w-8" />
                  </div>
                  <h3 className="text-xs font-bold text-zinc-600 mt-3">No AI API logs recorded</h3>
                  <p className="text-[10px] text-zinc-400 mt-1 max-w-xs leading-relaxed">
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
                      <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" horizontal={false} />
                      <XAxis
                        type="number"
                        tick={{ fontSize: 10, fill: "#94a3b8" }}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={formatTokens}
                      />
                      <YAxis
                        dataKey="name"
                        type="category"
                        tick={{ fontSize: 10, fill: "#94a3b8" }}
                        width={120}
                        tickLine={false}
                        axisLine={false}
                      />
                      <Tooltip
                        cursor={{ fill: "rgba(0,0,0,0.03)" }}
                        contentStyle={{
                          borderRadius: "12px",
                          fontSize: "11px",
                          borderColor: "#e4e4e7",
                        }}
                        formatter={(value) => [formatTokens(Number(value)), "Tokens"]}
                      />
                      <Bar dataKey="tokens" name="Total Tokens" fill="#059669" radius={[0, 4, 4, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              )}
            </div>
          </div>

          {/* Quick Actions */}
          <div className="bg-white border border-zinc-200 rounded-3xl p-5 shadow-sm">
            <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em] mb-4">
              Quick Actions
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Link
                href="/admin/users"
                className="group border border-zinc-200 hover:border-zinc-300 bg-zinc-50 p-4 rounded-2xl flex flex-col justify-between h-28 hover:shadow-md transition-all"
              >
                <div className="p-1.5 rounded-lg bg-white border border-zinc-200 text-zinc-500 group-hover:text-emerald-600 group-hover:border-emerald-100 group-hover:bg-emerald-50 w-fit transition-colors">
                  <Users className="h-4 w-4" />
                </div>
                <div>
                  <div className="text-xs font-bold text-zinc-800 flex items-center gap-1 group-hover:text-emerald-700 transition-colors">
                    Manage Accounts
                    <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                  </div>
                  <div className="text-[10px] text-zinc-500 mt-0.5">RBAC &amp; credentials setup</div>
                </div>
              </Link>

              <Link
                href="/admin/audit-logs"
                className="group border border-zinc-200 hover:border-zinc-300 bg-zinc-50 p-4 rounded-2xl flex flex-col justify-between h-28 hover:shadow-md transition-all"
              >
                <div className="p-1.5 rounded-lg bg-white border border-zinc-200 text-zinc-500 group-hover:text-amber-600 group-hover:border-amber-100 group-hover:bg-amber-50 w-fit transition-colors">
                  <Activity className="h-4 w-4" />
                </div>
                <div>
                  <div className="text-xs font-bold text-zinc-800 flex items-center gap-1 group-hover:text-amber-700 transition-colors">
                    Audit Log Browser
                    <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                  </div>
                  <div className="text-[10px] text-zinc-500 mt-0.5">Filter &amp; monitor operational actions</div>
                </div>
              </Link>

              <Link
                href="/admin/announcements"
                className="group border border-zinc-200 hover:border-zinc-300 bg-zinc-50 p-4 rounded-2xl flex flex-col justify-between h-28 hover:shadow-md transition-all"
              >
                <div className="p-1.5 rounded-lg bg-white border border-zinc-200 text-zinc-500 group-hover:text-sky-600 group-hover:border-sky-100 group-hover:bg-sky-50 w-fit transition-colors">
                  <Megaphone className="h-4 w-4" />
                </div>
                <div>
                  <div className="text-xs font-bold text-zinc-800 flex items-center gap-1 group-hover:text-sky-700 transition-colors">
                    Publish Feed
                    <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                  </div>
                  <div className="text-[10px] text-zinc-500 mt-0.5">Broadcast system updates</div>
                </div>
              </Link>
            </div>
          </div>
        </div>

        {/* Right: Activity feed */}
        <div className="bg-white border border-zinc-200 rounded-3xl overflow-hidden shadow-sm flex flex-col">
          <div className="px-5 py-4 border-b border-zinc-100 flex items-center justify-between gap-4">
            <div>
              <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em]">
                Recent Activity
              </h3>
              <p className="text-[10px] text-zinc-500 mt-1">
                Latest 5 system events logged on the server.
              </p>
            </div>
            <Link
              href="/admin/audit-logs"
              className="text-[10px] font-bold text-zinc-500 hover:text-zinc-900 transition-colors"
            >
              View All
            </Link>
          </div>

          <div className="p-5 flex-1 divide-y divide-zinc-100">
            {recentLogs.length === 0 ? (
              <div className="py-12 text-center">
                <div className="p-2.5 bg-zinc-50 border border-zinc-200 rounded-xl w-fit mx-auto text-zinc-400">
                  <Activity className="h-5 w-5" />
                </div>
                <h4 className="text-xs font-bold text-zinc-600 mt-3">No system activity</h4>
                <p className="text-[10px] text-zinc-400 mt-1">
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

                const eventTone: BadgeTone =
                  (log.event && eventTones[log.event]) || "zinc";

                return (
                  <div
                    key={log.id}
                    className="py-3.5 first:pt-0 last:pb-0 flex gap-3 text-xs leading-relaxed"
                  >
                    <div className="h-8 w-8 rounded-full bg-zinc-100 border border-zinc-200 text-zinc-600 font-bold text-[10px] flex items-center justify-center shrink-0">
                      {initials}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-1.5 flex-wrap">
                        <span className="font-semibold text-zinc-800">
                          {log.causer?.name || "System"}
                        </span>
                        {log.event && (
                          <Badge tone={eventTone}>{log.event}</Badge>
                        )}
                      </div>
                      <div className="text-zinc-500 text-[10px] mt-0.5 truncate">
                        {log.description}
                      </div>
                      <div className="flex items-center gap-2 mt-1 text-[9px] font-bold uppercase tracking-wider text-zinc-400">
                        <span>{log.log_name}</span>
                        <span>·</span>
                        <span>{relativeTime(log.created_at)}</span>
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
  );
}
