"use client";

import React, { useCallback, useEffect, useState } from "react";
import { CalendarCheck, Undo2, AlertTriangle, CheckCircle2, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  MealPrepLog,
  completeServiceDay,
  reverseServiceDay,
  listServiceLogs,
} from "@/services/consumptionService";

const peso = (v: string | number | null) =>
  v == null ? "—" : `₱${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/**
 * Self-contained "service day" consumption panel for an active menu cycle.
 * Marking a day served deducts that day's planned ingredients from inventory
 * (block-on-shortfall); the log can be reversed to restore stock.
 */
export default function ServiceLogPanel({ cycleId, population }: { cycleId: number; population: number }) {
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [logs, setLogs] = useState<MealPrepLog[]>([]);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [flash, setFlash] = useState<{ ok: boolean; msg: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try { setLogs(await listServiceLogs({ menu_cycle_id: cycleId })); }
    catch { /* keep prior */ }
    finally { setLoading(false); }
  }, [cycleId]);

  useEffect(() => { load(); }, [load]);

  const flashFor = (ok: boolean, msg: string) => { setFlash({ ok, msg }); setTimeout(() => setFlash(null), 6000); };

  async function markServed() {
    setBusy(true);
    try {
      const log = await completeServiceDay(cycleId, date, population || undefined);
      flashFor(true, `Served ${log.service_date} — ${peso(log.total_value)} of stock used.`);
      await load();
    } catch (e) {
      flashFor(false, e instanceof Error ? e.message : "Failed to complete service day.");
    } finally { setBusy(false); }
  }

  async function reverse(id: number) {
    setBusy(true);
    try { await reverseServiceDay(id); flashFor(true, "Service day reversed — stock restored."); await load(); }
    catch (e) { flashFor(false, e instanceof Error ? e.message : "Failed to reverse."); }
    finally { setBusy(false); }
  }

  return (
    <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-4">
      <div className="flex items-center gap-2">
        <CalendarCheck className="h-4 w-4 text-emerald-600" />
        <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Service log — deduct stock for a served day</h3>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <div>
          <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">Service date</label>
          <input type="date" value={date} onChange={(e) => setDate(e.target.value)}
            className="px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
        </div>
        <Button variant="primary" onClick={markServed} loading={busy} className="px-4 py-2 flex items-center gap-2">
          <CalendarCheck className="h-4 w-4" /> Mark served
        </Button>
      </div>

      {flash && (
        <div role="status" className={`flex items-start gap-2 text-xs font-bold px-3 py-2 rounded-xl border ${flash.ok ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-red-50 text-red-700 border-red-200"}`}>
          {flash.ok ? <CheckCircle2 className="h-3.5 w-3.5 mt-0.5 shrink-0" /> : <AlertTriangle className="h-3.5 w-3.5 mt-0.5 shrink-0" />}
          <span>{flash.msg}</span>
        </div>
      )}

      <div className="border-t border-zinc-100 pt-3">
        {loading ? (
          <div className="py-4 text-center text-xs text-zinc-400 flex items-center justify-center gap-2"><Loader2 className="h-4 w-4 animate-spin" /> Loading…</div>
        ) : logs.length === 0 ? (
          <p className="text-[11px] text-zinc-400">No days served yet for this cycle.</p>
        ) : (
          <table className="w-full text-xs">
            <thead className="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
              <tr><th className="text-left py-1.5">Date</th><th className="text-left py-1.5">Value used</th><th className="text-left py-1.5">Status</th><th /></tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {logs.map((l) => (
                <tr key={l.id}>
                  <td className="py-2 font-semibold text-zinc-800">{l.service_date}</td>
                  <td className="py-2 font-mono text-zinc-600">{peso(l.total_value)}</td>
                  <td className="py-2">
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${l.status === "completed" ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-zinc-100 text-zinc-500 border-zinc-200"}`}>{l.status}</span>
                  </td>
                  <td className="py-2 text-right">
                    {l.status === "completed" && (
                      <button onClick={() => reverse(l.id)} disabled={busy}
                        className="inline-flex items-center gap-1 text-[10px] font-bold text-zinc-500 hover:text-red-600 disabled:opacity-50">
                        <Undo2 className="h-3 w-3" /> Reverse
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
