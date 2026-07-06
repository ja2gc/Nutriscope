"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { Bell, Cog, Palette, Wallet } from "lucide-react";
import { PageHeader } from "@/components/ui/PageHeader";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { getFoodServiceSetting, setFoodServiceSetting } from "@/services/budgetService";
import {
  Density,
  getAnnouncementNotifications,
  getDensity,
  getFollowUpNotifications,
  setAnnouncementNotifications,
  setDensity as persistDensity,
  setFollowUpNotifications,
} from "@/lib/preferences";

// Budget per head per day — shared Food Service setting (backend-backed).
function PerHeadDayLimitCard({ prefix }: { prefix: "fss" | "admin" }) {
  const [value, setValue] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  useEffect(() => {
    getFoodServiceSetting(prefix)
      .then((s) => setValue(s.per_head_day_limit ?? ""))
      .catch(() => setMsg("Failed to load setting."))
      .finally(() => setLoading(false));
  }, [prefix]);

  async function save() {
    setSaving(true);
    setMsg(null);
    try {
      const parsed = value.trim() === "" ? null : parseFloat(value);
      const saved = await setFoodServiceSetting(parsed, prefix);
      setValue(saved.per_head_day_limit ?? "");
      setMsg("Saved.");
    } catch (e) {
      setMsg(e instanceof Error ? e.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="p-6 space-y-5">
      <h3 className="text-sm font-bold text-warm-900 uppercase tracking-wider flex items-center gap-2">
        <Wallet className="h-4 w-4 text-brand-green-600" />
        Food Service Budget
      </h3>
      <div className="space-y-2">
        <span className="text-sm font-semibold text-warm-600">Budget per head per day (₱)</span>
        <input
          type="number" min="0" step="0.01"
          value={value}
          disabled={loading}
          onChange={(e) => setValue(e.target.value)}
          placeholder="0.00"
          className="w-full px-3 py-2 text-base border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green-500"
        />
      </div>
      <div className="flex items-center gap-3">
        <Button onClick={save} disabled={saving || loading} className="text-base">
          {saving ? "Saving…" : "Save"}
        </Button>
        {msg && <span className="text-sm text-warm-500">{msg}</span>}
      </div>
    </Card>
  );
}

export default function SettingsPage() {
  const [density, setDensityState] = useState<Density>("comfortable");
  const [announcementAlerts, setAnnouncementAlerts] = useState(true);
  const [followUpAlerts, setFollowUpAlerts] = useState(true);

  useEffect(() => {
    setDensityState(getDensity());
    setAnnouncementAlerts(getAnnouncementNotifications());
    setFollowUpAlerts(getFollowUpNotifications());
  }, []);

  function chooseDensity(value: Density) {
    setDensityState(value);
    persistDensity(value);
  }

  function toggleAnnouncements() {
    const next = !announcementAlerts;
    setAnnouncementAlerts(next);
    setAnnouncementNotifications(next);
  }

  function toggleFollowUps() {
    const next = !followUpAlerts;
    setFollowUpAlerts(next);
    setFollowUpNotifications(next);
  }

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={[["Home", "/dashboard"], ["Settings"]]}
        title="Settings"
        icon={<Cog className="h-5 w-5 text-brand-green-600" />}
        subtitle="Display preferences are saved on this device. Account details live on your Profile."
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <Card className="p-6 space-y-5">
          <h3 className="text-sm font-bold text-warm-900 uppercase tracking-wider flex items-center gap-2">
            <Palette className="h-4 w-4 text-brand-green-600" />
            Appearance
          </h3>

          <div className="space-y-2">
            <span className="text-sm font-semibold text-warm-600">Density</span>
            <div className="grid grid-cols-2 gap-2">
              {(["comfortable", "compact"] as Density[]).map((value) => (
                <button
                  key={value}
                  onClick={() => chooseDensity(value)}
                  className={`px-3 py-2.5 rounded-lg text-sm font-bold uppercase tracking-wider border transition-colors ${
                    density === value
                      ? "border-brand-green-600 bg-brand-green-50 text-brand-green-800"
                      : "border-warm-200 text-warm-500 hover:bg-warm-50"
                  }`}
                >
                  {value}
                </button>
              ))}
            </div>
          </div>
        </Card>

        <Card className="p-6 space-y-5">
          <h3 className="text-sm font-bold text-warm-900 uppercase tracking-wider flex items-center gap-2">
            <Bell className="h-4 w-4 text-brand-green-600" />
            Notifications
          </h3>
          <label className="flex items-center justify-between gap-3 cursor-pointer">
            <span className="text-sm font-semibold text-warm-600">New announcements</span>
            <button role="switch" aria-checked={announcementAlerts} onClick={toggleAnnouncements} className={`relative h-5 w-9 rounded-full transition-colors ${announcementAlerts ? "bg-brand-green-600" : "bg-warm-300"}`}>
              <span className={`absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform ${announcementAlerts ? "translate-x-4" : "translate-x-0"}`} />
            </button>
          </label>
          <label className="flex items-center justify-between gap-3 cursor-pointer">
            <span className="text-sm font-semibold text-warm-600">Follow-up reminders</span>
            <button role="switch" aria-checked={followUpAlerts} onClick={toggleFollowUps} className={`relative h-5 w-9 rounded-full transition-colors ${followUpAlerts ? "bg-brand-green-600" : "bg-warm-300"}`}>
              <span className={`absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform ${followUpAlerts ? "translate-x-4" : "translate-x-0"}`} />
            </button>
          </label>
          <Link href="/notifications" className="text-sm font-semibold text-brand-green-700 hover:text-brand-green-800">
            Open notifications
          </Link>
        </Card>

        <PerHeadDayLimitCard prefix="fss" />
      </div>
    </div>
  );
}
