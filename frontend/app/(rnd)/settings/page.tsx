"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { Sliders, Palette, Bell, CheckCheck, Zap } from "lucide-react";
import { PageHeader } from "@/components/ui/PageHeader";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import {
  Density,
  getDensity,
  getReduceMotion,
  setDensity as persistDensity,
  setReduceMotion as persistReduceMotion,
} from "@/lib/preferences";
import { markAllNotificationsRead } from "@/services/notificationService";

export default function SettingsPage() {
  const [density, setDensityState] = useState<Density>("comfortable");
  const [reduceMotion, setReduceMotionState] = useState(false);
  const [markingAll, setMarkingAll] = useState(false);
  const [markedAll, setMarkedAll] = useState(false);

  // Hydrate from localStorage after mount (avoids SSR mismatch).
  useEffect(() => {
    setDensityState(getDensity());
    setReduceMotionState(getReduceMotion());
  }, []);

  function chooseDensity(value: Density) {
    setDensityState(value);
    persistDensity(value);
  }

  function toggleReduceMotion() {
    const next = !reduceMotion;
    setReduceMotionState(next);
    persistReduceMotion(next);
  }

  async function handleMarkAll() {
    setMarkingAll(true);
    setMarkedAll(false);
    try {
      await markAllNotificationsRead();
      setMarkedAll(true);
    } catch {
      // Non-fatal.
    } finally {
      setMarkingAll(false);
    }
  }

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={[["Home", "/dashboard"], ["Settings"]]}
        title="Settings & Preferences"
        icon={<Sliders className="h-5 w-5 text-emerald-600" />}
        subtitle="Display preferences are saved on this device. Account details live on your Profile."
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        {/* Appearance */}
        <Card className="p-6 space-y-5">
          <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-2">
            <Palette className="h-4 w-4 text-emerald-600" />
            Appearance
          </h3>

          <div className="space-y-2">
            <span className="text-xs font-semibold text-zinc-600">Density</span>
            <div className="grid grid-cols-2 gap-2">
              {(["comfortable", "compact"] as Density[]).map((value) => (
                <button
                  key={value}
                  onClick={() => chooseDensity(value)}
                  className={`px-3 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider border transition-colors ${
                    density === value
                      ? "border-emerald-600 bg-emerald-50 text-emerald-800"
                      : "border-zinc-200 text-zinc-500 hover:bg-zinc-50"
                  }`}
                >
                  {value}
                </button>
              ))}
            </div>
            <p className="text-[10px] text-zinc-400">Compact tightens spacing and text across the app.</p>
          </div>

          <label className="flex items-center justify-between gap-3 pt-1 cursor-pointer">
            <span className="flex items-center gap-2 text-xs font-semibold text-zinc-600">
              <Zap className="h-4 w-4 text-zinc-400" />
              Reduce motion
            </span>
            <button
              role="switch"
              aria-checked={reduceMotion}
              onClick={toggleReduceMotion}
              className={`relative h-5 w-9 rounded-full transition-colors ${reduceMotion ? "bg-emerald-600" : "bg-zinc-300"}`}
            >
              <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-transform ${reduceMotion ? "translate-x-4" : "translate-x-0.5"}`} />
            </button>
          </label>
        </Card>

        {/* Notifications */}
        <Card className="p-6 space-y-5">
          <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-2">
            <Bell className="h-4 w-4 text-emerald-600" />
            Notifications
          </h3>
          <p className="text-xs text-zinc-500 leading-relaxed">
            You receive notifications for new announcements and follow-ups scheduled for the next day.
          </p>
          <div className="flex flex-wrap items-center gap-3">
            <Button variant="secondary" onClick={handleMarkAll} loading={markingAll} className="w-auto">
              <CheckCheck className="h-4 w-4" />
              Mark all as read
            </Button>
            <Link href="/notifications" className="text-xs font-semibold text-emerald-700 hover:text-emerald-800">
              Open notifications →
            </Link>
            {markedAll && <span className="text-xs font-semibold text-emerald-600">Done.</span>}
          </div>
        </Card>
      </div>
    </div>
  );
}
