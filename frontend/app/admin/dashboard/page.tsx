"use client";

import React, { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { fetchDashboard, DashboardData } from "@/services/adminDashboardService";
import { listAuditLogs, AuditLog } from "@/services/auditLogService";
import {
  getAiUsageLimits,
  saveAiUsageLimits,
  AiUsageLimits,
} from "@/services/aiUsageLimitService";
import { fetchUsdToPhpRate } from "@/services/currencyService";
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

// Haiku: $0.80/MTok input · $4.00/MTok output
const HAIKU_INPUT_PER_TOKEN  = 0.80 / 1_000_000;
const HAIKU_OUTPUT_PER_TOKEN = 4.00 / 1_000_000;
// Blended rate for when only totals are available (~65% input / 35% output)
const HAIKU_BLENDED_PER_TOKEN = (0.65 * 0.80 + 0.35 * 4.00) / 1_000_000;

function calcExactCost(inputTokens: number, outputTokens: number): number {
  return inputTokens * HAIKU_INPUT_PER_TOKEN + outputTokens * HAIKU_OUTPUT_PER_TOKEN;
}

function calcBlendedCost(totalTokens: number): number {
  return totalTokens * HAIKU_BLENDED_PER_TOKEN;
}

function formatCost(usd: number, phpRate: number): string {
  const php = usd * phpRate;
  if (php === 0) return "₱0.00";
  if (php < 0.01)  return `₱${php.toFixed(4)}`;
  if (php < 1)     return `₱${php.toFixed(3)}`;
  return `₱${php.toFixed(2)}`;
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

  const [phpRate, setPhpRate] = useState<number>(56);

  // AI token cap state
  const [aiLimits, setAiLimits] = useState<AiUsageLimits | null>(null);
  const [capDailyInput, setCapDailyInput] = useState("");
  const [capMonthlyInput, setCapMonthlyInput] = useState("");
  const [capSaving, setCapSaving] = useState(false);
  const [capSaveMsg, setCapSaveMsg] = useState<{ ok: boolean; text: string } | null>(null);

  async function loadAiLimits() {
    try {
      const limits = await getAiUsageLimits();
      setAiLimits(limits);
      setCapDailyInput(limits.daily_token_limit != null ? String(limits.daily_token_limit) : "");
      setCapMonthlyInput(limits.monthly_token_limit != null ? String(limits.monthly_token_limit) : "");
    } catch {
      // non-fatal — card shows blank
    }
  }

  async function handleSaveCaps() {
    setCapSaving(true);
    setCapSaveMsg(null);
    try {
      const payload = {
        daily_token_limit: capDailyInput.trim() === "" ? null : parseInt(capDailyInput, 10),
        monthly_token_limit: capMonthlyInput.trim() === "" ? null : parseInt(capMonthlyInput, 10),
      };
      const updated = await saveAiUsageLimits(payload);
      setAiLimits(updated);
      setCapDailyInput(updated.daily_token_limit != null ? String(updated.daily_token_limit) : "");
      setCapMonthlyInput(updated.monthly_token_limit != null ? String(updated.monthly_token_limit) : "");
      setCapSaveMsg({ ok: true, text: "Limits saved." });
    } catch (err: unknown) {
      setCapSaveMsg({ ok: false, text: err instanceof Error ? err.message : "Failed to save limits." });
    } finally {
      setCapSaving(false);
    }
  }

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
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to load admin dashboard data.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }

  useEffect(() => {
    void loadData();
    void loadAiLimits();
    void fetchUsdToPhpRate().then(setPhpRate);
  }, []);

  const chartData = useMemo(() => {
    if (!dashboardData?.ai_usage?.daily) return [];
    return dashboardData.ai_usage.daily.map((point) => ({
      name: new Date(point.date).toLocaleDateString("en-US", { month: "short", day: "numeric" }),
      tokens: point.tokens,
      calls: point.calls,
    }));
  }, [dashboardData]);

  if (loading) {
    return (
      <div className="space-y-6 animate-pulse">
        <div className="h-8 w-48 bg-warm-100 rounded-lg" />
        <div className="h-4 w-96 bg-warm-100 rounded-lg" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-24 bg-warm-100 border border-warm-200 rounded-2xl" />
          ))}
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
          <div className="lg:col-span-2 h-96 bg-warm-100 border border-warm-200 rounded-3xl" />
          <div className="h-96 bg-warm-100 border border-warm-200 rounded-3xl" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 font-sans">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-warm-200 pb-5">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-warm-400 select-none">
            <span>Admin</span>
            <span className="text-warm-300">/</span>
            <span className="text-warm-600 font-bold">Dashboard</span>
          </div>
          <h1 className="text-xl font-extrabold text-warm-900 tracking-tight mt-1 flex items-center gap-2.5">
            <LayoutDashboard className="h-5 w-5 text-emerald-600" />
            Admin Console
          </h1>
          <p className="text-xs text-warm-500 mt-0.5 select-none">
            System configuration, active directories, AI consumption metrics, and logs.
          </p>
        </div>
        <button
          onClick={() => void loadData(true)}
          disabled={refreshing}
          className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-warm-200 bg-white text-xs font-bold uppercase tracking-wider text-warm-600 hover:text-warm-900 hover:bg-warm-50 active:bg-warm-100 transition-colors disabled:opacity-50 shadow-sm"
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
            tone="zinc"
          />
          <KpiCard
            label="Patients in Care"
            value={formatNumber(dashboardData.patients.total)}
            hint="Clinical records tracked"
            tone="zinc"
          />
          <KpiCard
            label="AI Usage (Month)"
            value={formatNumber(dashboardData.ai_usage.month_calls)}
            hint={`${formatTokens(dashboardData.ai_usage.month_tokens)} tokens · ${formatCost(calcExactCost(dashboardData.ai_usage.month_tokens_input, dashboardData.ai_usage.month_tokens_output), phpRate)} est. cost`}
            tone="zinc"
          />
          <KpiCard
            label="Audit Events"
            value={formatNumber(dashboardData.audit_logs.total)}
            hint={`+${dashboardData.audit_logs.last_7_days} in last 7 days`}
            tone="zinc"
          />
        </div>
      )}

      {/* AI Token Cap Card */}
      <div className="bg-white border border-warm-200 rounded-3xl overflow-hidden shadow-sm">
        <div className="px-5 py-4 border-b border-warm-100 flex items-center gap-3">
          <Cpu className="h-4 w-4 text-emerald-600 shrink-0" />
          <div>
            <h3 className="text-xs font-bold text-warm-900 uppercase tracking-[0.18em]">
              AI Token Caps
            </h3>
            <p className="text-[10px] text-warm-500 mt-0.5">
              Set daily and monthly limits. Leave blank for unlimited.
            </p>
          </div>
        </div>

        <div className="p-5 space-y-4">
          {/* Usage rows */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {/* Daily */}
            {(() => {
              const used = aiLimits?.daily_used ?? 0;
              const cap = aiLimits?.daily_token_limit ?? null;
              const over = cap !== null && used >= cap;
              return (
                <div className={`rounded-2xl border px-4 py-3 ${over ? "border-amber-200 bg-amber-50" : "border-warm-100 bg-warm-50"}`}>
                  <div className="text-[10px] font-bold uppercase tracking-wider text-warm-400 mb-1">Daily</div>
                  <div className={`text-lg font-bold tabular-nums leading-none ${over ? "text-amber-700" : "text-warm-900"}`}>
                    {formatTokens(used)}
                  </div>
                  <div className="text-[10px] text-warm-500 mt-0.5">
                    of {cap !== null ? formatTokens(cap) : "Unlimited"}
                    {over && <span className="ml-1.5 font-bold text-amber-600">· over cap</span>}
                  </div>
                  <div className="text-[10px] text-warm-400 mt-1">
                    {formatCost(calcBlendedCost(used), phpRate)} est. cost
                  </div>
                </div>
              );
            })()}

            {/* Monthly */}
            {(() => {
              const used = aiLimits?.monthly_used ?? 0;
              const cap = aiLimits?.monthly_token_limit ?? null;
              const over = cap !== null && used >= cap;
              return (
                <div className={`rounded-2xl border px-4 py-3 ${over ? "border-amber-200 bg-amber-50" : "border-warm-100 bg-warm-50"}`}>
                  <div className="text-[10px] font-bold uppercase tracking-wider text-warm-400 mb-1">Monthly</div>
                  <div className={`text-lg font-bold tabular-nums leading-none ${over ? "text-amber-700" : "text-warm-900"}`}>
                    {formatTokens(used)}
                  </div>
                  <div className="text-[10px] text-warm-500 mt-0.5">
                    of {cap !== null ? formatTokens(cap) : "Unlimited"}
                    {over && <span className="ml-1.5 font-bold text-amber-600">· over cap</span>}
                  </div>
                  <div className="text-[10px] text-warm-400 mt-1">
                    {formatCost(calcBlendedCost(used), phpRate)} est. cost
                  </div>
                </div>
              );
            })()}
          </div>

          {/* Inline edit */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-[10px] font-bold uppercase tracking-wider text-warm-500 mb-1">
                Daily limit (tokens)
              </label>
              <input
                type="number"
                min={0}
                placeholder="Unlimited"
                value={capDailyInput}
                onChange={(e) => { setCapDailyInput(e.target.value); setCapSaveMsg(null); }}
                disabled={capSaving}
                className="w-full text-xs font-semibold tabular-nums rounded-xl border border-warm-200 bg-warm-50 px-3 py-2 text-warm-800 placeholder:text-warm-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400 disabled:opacity-50 transition"
              />
            </div>
            <div>
              <label className="block text-[10px] font-bold uppercase tracking-wider text-warm-500 mb-1">
                Monthly limit (tokens)
              </label>
              <input
                type="number"
                min={0}
                placeholder="Unlimited"
                value={capMonthlyInput}
                onChange={(e) => { setCapMonthlyInput(e.target.value); setCapSaveMsg(null); }}
                disabled={capSaving}
                className="w-full text-xs font-semibold tabular-nums rounded-xl border border-warm-200 bg-warm-50 px-3 py-2 text-warm-800 placeholder:text-warm-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400 disabled:opacity-50 transition"
              />
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button
              onClick={() => void handleSaveCaps()}
              disabled={capSaving}
              className="inline-flex items-center gap-2 px-4 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-emerald-700 active:bg-emerald-800 disabled:opacity-50 transition-colors shadow-sm"
            >
              {capSaving ? (
                <>
                  <RefreshCw className="h-3 w-3 animate-spin" />
                  Saving…
                </>
              ) : (
                "Save Limits"
              )}
            </button>
            {capSaveMsg && (
              <span className={`text-[10px] font-semibold ${capSaveMsg.ok ? "text-emerald-600" : "text-red-600"}`}>
                {capSaveMsg.text}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Main Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: AI usage chart + Quick Actions */}
        <div className="lg:col-span-2 space-y-6">
          {/* Token-usage chart */}
          <div className="bg-white border border-warm-200 rounded-3xl overflow-hidden shadow-sm">
            <div className="px-5 py-4 border-b border-warm-100 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-xs font-bold text-warm-900 uppercase tracking-[0.18em]">
                  AI Token Consumption
                </h3>
                <p className="text-[10px] text-warm-500 mt-1">
                  Tokens per day · last 30 days.
                </p>
              </div>
              <Badge tone="zinc">Daily</Badge>
            </div>

            <div className="p-6">
              {chartData.length === 0 ? (
                <div className="h-72 flex flex-col items-center justify-center text-center">
                  <div className="p-3 bg-warm-50 border border-warm-200 rounded-2xl text-warm-400">
                    <Cpu className="h-8 w-8" />
                  </div>
                  <h3 className="text-xs font-bold text-warm-600 mt-3">No AI usage recorded yet</h3>
                  <p className="text-[10px] text-warm-400 mt-1 max-w-xs leading-relaxed">
                    AI generation tokens will map here once RND clinicians trigger automated NCP processes.
                  </p>
                </div>
              ) : (
                <div className="h-72 w-full">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                      data={chartData}
                      margin={{ top: 5, right: 16, left: 0, bottom: 5 }}
                    >
                      <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" vertical={false} />
                      <XAxis
                        dataKey="name"
                        tick={{ fontSize: 10, fill: "#94a3b8" }}
                        tickLine={false}
                        axisLine={false}
                        minTickGap={16}
                      />
                      <YAxis
                        tick={{ fontSize: 10, fill: "#94a3b8" }}
                        tickLine={false}
                        axisLine={false}
                        width={44}
                        tickFormatter={formatTokens}
                      />
                      <Tooltip
                        cursor={{ fill: "rgba(0,0,0,0.03)" }}
                        contentStyle={{
                          borderRadius: "12px",
                          fontSize: "11px",
                          borderColor: "#e4e4e7",
                        }}
                        formatter={(value) => [`${formatTokens(Number(value))} · ${formatCost(calcBlendedCost(Number(value)), phpRate)} est.`, "Tokens"]}
                      />
                      <Bar dataKey="tokens" name="Tokens" fill="#059669" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              )}
            </div>
          </div>

          {/* Quick Actions */}
          <div className="bg-white border border-warm-200 rounded-3xl p-5 shadow-sm">
            <h3 className="text-xs font-bold text-warm-900 uppercase tracking-[0.18em] mb-4">
              Quick Actions
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Link
                href="/admin/users"
                className="group border border-warm-200 hover:border-warm-300 bg-warm-50 p-4 rounded-2xl flex flex-col justify-between h-28 hover:shadow-md transition-all"
              >
                <div className="p-1.5 rounded-lg bg-white border border-warm-200 text-warm-500 group-hover:text-emerald-600 group-hover:border-emerald-100 group-hover:bg-emerald-50 w-fit transition-colors">
                  <Users className="h-4 w-4" />
                </div>
                <div>
                  <div className="text-xs font-bold text-warm-800 flex items-center gap-1 group-hover:text-emerald-700 transition-colors">
                    Manage Accounts
                    <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                  </div>
                  <div className="text-[10px] text-warm-500 mt-0.5">RBAC &amp; credentials setup</div>
                </div>
              </Link>

              <Link
                href="/admin/audit-logs"
                className="group border border-warm-200 hover:border-warm-300 bg-warm-50 p-4 rounded-2xl flex flex-col justify-between h-28 hover:shadow-md transition-all"
              >
                <div className="p-1.5 rounded-lg bg-white border border-warm-200 text-warm-500 group-hover:text-amber-600 group-hover:border-amber-100 group-hover:bg-amber-50 w-fit transition-colors">
                  <Activity className="h-4 w-4" />
                </div>
                <div>
                  <div className="text-xs font-bold text-warm-800 flex items-center gap-1 group-hover:text-amber-700 transition-colors">
                    Audit Log Browser
                    <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                  </div>
                  <div className="text-[10px] text-warm-500 mt-0.5">Filter &amp; monitor operational actions</div>
                </div>
              </Link>

              <Link
                href="/admin/announcements"
                className="group border border-warm-200 hover:border-warm-300 bg-warm-50 p-4 rounded-2xl flex flex-col justify-between h-28 hover:shadow-md transition-all"
              >
                <div className="p-1.5 rounded-lg bg-white border border-warm-200 text-warm-500 group-hover:text-sky-600 group-hover:border-sky-100 group-hover:bg-sky-50 w-fit transition-colors">
                  <Megaphone className="h-4 w-4" />
                </div>
                <div>
                  <div className="text-xs font-bold text-warm-800 flex items-center gap-1 group-hover:text-sky-700 transition-colors">
                    Publish Feed
                    <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                  </div>
                  <div className="text-[10px] text-warm-500 mt-0.5">Broadcast system updates</div>
                </div>
              </Link>
            </div>
          </div>
        </div>

        {/* Right: Activity feed */}
        <div className="bg-white border border-warm-200 rounded-3xl overflow-hidden shadow-sm flex flex-col">
          <div className="px-5 py-4 border-b border-warm-100 flex items-center justify-between gap-4">
            <div>
              <h3 className="text-xs font-bold text-warm-900 uppercase tracking-[0.18em]">
                Recent Activity
              </h3>
              <p className="text-[10px] text-warm-500 mt-1">
                Latest 5 system events logged on the server.
              </p>
            </div>
            <Link
              href="/admin/audit-logs"
              className="text-[10px] font-bold text-warm-500 hover:text-warm-900 transition-colors"
            >
              View All
            </Link>
          </div>

          <div className="p-5 flex-1 divide-y divide-zinc-100">
            {recentLogs.length === 0 ? (
              <div className="py-12 text-center">
                <div className="p-2.5 bg-warm-50 border border-warm-200 rounded-xl w-fit mx-auto text-warm-400">
                  <Activity className="h-5 w-5" />
                </div>
                <h4 className="text-xs font-bold text-warm-600 mt-3">No system activity</h4>
                <p className="text-[10px] text-warm-400 mt-1">
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
                    <div className="h-8 w-8 rounded-full bg-warm-100 border border-warm-200 text-warm-600 font-bold text-[10px] flex items-center justify-center shrink-0">
                      {initials}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-1.5 flex-wrap">
                        <span className="font-semibold text-warm-800">
                          {log.causer?.name || "System"}
                        </span>
                        {log.event && (
                          <Badge tone={eventTone}>{log.event}</Badge>
                        )}
                      </div>
                      <div className="text-warm-500 text-[10px] mt-0.5 truncate">
                        {log.description}
                      </div>
                      <div className="flex items-center gap-2 mt-1 text-[9px] font-bold uppercase tracking-wider text-warm-400">
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
