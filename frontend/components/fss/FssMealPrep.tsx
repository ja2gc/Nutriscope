"use client";

import { CheckCircle2, RotateCcw, Utensils } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { completeServiceDay, listServiceLogs, reverseServiceDay, type MealPrepLog } from "@/services/consumptionService";
import { getCycle, listCycles, type MenuCycle } from "@/services/menuCycleService";
import { serviceDateForDay, validPopulation } from "@/lib/fssMealPrep";

interface ServiceDay {
  weekday: string;
  serviceDate: string;
  meals: number;
}

export function FssMealPrep() {
  const [cycle, setCycle] = useState<MenuCycle | null>(null);
  const [logs, setLogs] = useState<MealPrepLog[]>([]);
  const [populations, setPopulations] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const cycles = await listCycles(1);
      const active = cycles.data.find((item) => item.is_active) ?? null;
      if (!active) {
        setCycle(null);
        setLogs([]);
        return;
      }
      const fullCycle = await getCycle(active.id);
      const serviceLogs = await listServiceLogs({ menu_cycle_id: active.id });
      setCycle(fullCycle);
      setLogs(serviceLogs);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to load meal preparation.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const days = useMemo<ServiceDay[]>(() => {
    if (!cycle?.week_start_date) return [];
    const grouped = new Map<string, number>();
    for (const day of cycle.days ?? []) grouped.set(day.day_of_week, (grouped.get(day.day_of_week) ?? 0) + 1);
    return Array.from(grouped, ([weekday, meals]) => ({
      weekday,
      meals,
      serviceDate: serviceDateForDay(cycle.week_start_date!, weekday) ?? "",
    })).filter((day) => day.serviceDate).sort((a, b) => a.serviceDate.localeCompare(b.serviceDate));
  }, [cycle]);

  async function complete(day: ServiceDay) {
    if (!cycle) return;
    const population = validPopulation(populations[day.serviceDate] ?? "");
    if (!population) {
      setError("Enter a positive whole-number served population.");
      return;
    }
    setBusy(day.serviceDate);
    setError(null);
    try {
      await completeServiceDay(cycle.id, day.serviceDate, population);
      await load();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to complete service day.");
    } finally {
      setBusy(null);
    }
  }

  async function reverse(log: MealPrepLog) {
    if (!window.confirm(`Reverse the completed service record for ${log.service_date}?`)) return;
    setBusy(log.service_date);
    setError(null);
    try {
      await reverseServiceDay(log.id);
      await load();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to reverse service day.");
    } finally {
      setBusy(null);
    }
  }

  if (loading) return <p role="status" className="py-16 text-center text-sm font-semibold text-warm-500">Loading meal preparation…</p>;

  return (
    <div className="space-y-5">
      <div><p className="text-sm font-bold text-emerald-700">Execution</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight">Meal preparation</h1><p className="mt-1 text-sm leading-6 text-warm-500">Record served population when a service day is complete.</p></div>

      {error && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">{error}</div>}
      {!cycle ? <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-800">No active menu cycle. Contact RND.</div> : null}

      <div className="space-y-3">
        {days.map((day) => {
          const completed = logs.find((log) => log.service_date === day.serviceDate && log.status === "completed");
          return (
            <section key={day.serviceDate} className="rounded-2xl border border-warm-200 bg-white p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div><p className="text-base font-extrabold">{day.weekday}</p><p className="text-sm text-warm-500">{new Date(`${day.serviceDate}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" })} · {day.meals} meal slots</p></div>
                {completed && <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800"><CheckCircle2 className="h-3.5 w-3.5" />Completed</span>}
              </div>

              {completed ? (
                <div className="mt-4 flex items-end justify-between gap-3 border-t border-warm-100 pt-4">
                  <div><p className="text-xs font-bold uppercase text-warm-400">Served population</p><p className="text-lg font-extrabold">{completed.served_population ?? "—"}</p><p className="text-sm text-warm-500">Value: ₱{Number(completed.total_value ?? 0).toLocaleString("en-PH", { minimumFractionDigits: 2 })}</p></div>
                  <button type="button" disabled={busy === day.serviceDate} onClick={() => void reverse(completed)} className="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"><RotateCcw className="h-4 w-4" />Reverse</button>
                </div>
              ) : (
                <div className="mt-4 grid gap-3 border-t border-warm-100 pt-4 sm:grid-cols-[1fr_auto] sm:items-end">
                  <label className="text-sm font-bold text-warm-700">Served population<input type="number" min="1" step="1" inputMode="numeric" value={populations[day.serviceDate] ?? ""} onChange={(event) => setPopulations((current) => ({ ...current, [day.serviceDate]: event.target.value }))} className="mt-1 min-h-11 w-full rounded-xl border border-warm-200 px-3 text-base focus:outline-none focus:ring-2 focus:ring-emerald-600" /></label>
                  <button type="button" disabled={busy === day.serviceDate} onClick={() => void complete(day)} className="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"><Utensils className="h-4 w-4" />Complete service day</button>
                </div>
              )}
            </section>
          );
        })}
      </div>
    </div>
  );
}
