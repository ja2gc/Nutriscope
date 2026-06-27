"use client";

import React, { useEffect, useRef, useState } from "react";
import {
  Bell,
  Building2,
  Palette,
  Settings,
  Wallet,
} from "lucide-react";
import { PageHeader } from "@/components/ui/PageHeader";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { getFoodServiceSetting, setFoodServiceSetting } from "@/services/budgetService";

// Budget per head per day — shared Food Service setting (backend-backed).
function PerHeadDayLimitCard() {
  const [value, setValue] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  useEffect(() => {
    getFoodServiceSetting("admin")
      .then((s) => setValue(s.per_head_day_limit ?? ""))
      .catch(() => setMsg("Failed to load setting."))
      .finally(() => setLoading(false));
  }, []);

  async function save() {
    setSaving(true);
    setMsg(null);
    try {
      const parsed = value.trim() === "" ? null : parseFloat(value);
      const saved = await setFoodServiceSetting(parsed, "admin");
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
      <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-2">
        <Wallet className="h-4 w-4 text-emerald-600" />
        Food Service Budget
      </h3>
      <div className="space-y-2">
        <span className="text-xs font-semibold text-zinc-600">Budget per head per day (₱)</span>
        <input
          type="number" min="0" step="0.01"
          value={value}
          disabled={loading}
          onChange={(e) => setValue(e.target.value)}
          placeholder="0.00"
          className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
      </div>
      <div className="flex items-center gap-3">
        <Button onClick={save} disabled={saving || loading} className="text-sm">
          {saving ? "Saving…" : "Save"}
        </Button>
        {msg && <span className="text-xs text-zinc-500">{msg}</span>}
      </div>
    </Card>
  );
}
import {
  Density,
  getAnnouncementNotifications,
  getDensity,
  getFollowUpNotifications,
  setAnnouncementNotifications,
  setDensity as persistDensity,
  setFollowUpNotifications,
} from "@/lib/preferences";
import {
  Branding,
  getAdminBranding,
  saveAdminBranding,
} from "@/services/reportService";

export default function AdminSettingsPage() {
  // ── Appearance ──────────────────────────────────────────────────
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

  // ── Notifications ────────────────────────────────────────────────
  // ── Branding ─────────────────────────────────────────────────────
  const [branding, setBranding] = useState<Branding | null>(null);
  const [brandingLoading, setBrandingLoading] = useState(true);
  const [brandingError, setBrandingError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [savedOk, setSavedOk] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<
    Record<string, string>
  >({});

  // Controlled text fields
  const [hospitalName, setHospitalName] = useState("");
  const [address, setAddress] = useState("");
  const [accreditation, setAccreditation] = useState("");
  const [serviceName, setServiceName] = useState("");
  const [province, setProvince] = useState("");
  const [lgu, setLgu] = useState("");

  // File refs (uncontrolled — browser file input)
  const logoLeftRef = useRef<HTMLInputElement>(null);
  const logoRightRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    setBrandingLoading(true);
    getAdminBranding()
      .then((b) => {
        setBranding(b);
        setHospitalName(b.hospital_name ?? "");
        setAddress(b.address ?? "");
        setAccreditation(b.accreditation ?? "");
        setServiceName(b.service_name ?? "");
        setProvince(b.province ?? "");
        setLgu(b.lgu ?? "");
      })
      .catch((err) =>
        setBrandingError(
          err instanceof Error ? err.message : "Failed to load branding.",
        ),
      )
      .finally(() => setBrandingLoading(false));
  }, []);

  async function handleBrandingSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setSavedOk(false);
    setSaveError(null);
    setValidationErrors({});

    const form = new FormData();
    form.append("hospital_name", hospitalName);
    form.append("address", address);
    form.append("accreditation", accreditation);
    form.append("service_name", serviceName);
    form.append("province", province);
    form.append("lgu", lgu);
    const ll = logoLeftRef.current?.files?.[0];
    const lr = logoRightRef.current?.files?.[0];
    if (ll) form.append("logo_left", ll);
    if (lr) form.append("logo_right", lr);

    try {
      const updated = await saveAdminBranding(form);
      setBranding(updated);
      setSavedOk(true);
      // Clear file inputs after a successful save
      if (logoLeftRef.current) logoLeftRef.current.value = "";
      if (logoRightRef.current) logoRightRef.current.value = "";
    } catch (err) {
      const msg = err instanceof Error ? err.message : "Failed to save.";
      // Try to parse Laravel validation errors embedded in the message
      try {
        const parsed = JSON.parse(msg);
        if (parsed?.errors) {
          const flat: Record<string, string> = {};
          for (const [k, v] of Object.entries(parsed.errors as Record<string, string[]>)) {
            flat[k] = Array.isArray(v) ? v[0] : String(v);
          }
          setValidationErrors(flat);
          setSaveError("Please fix the errors below.");
        } else {
          setSaveError(msg);
        }
      } catch {
        setSaveError(msg);
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={[["Admin", "/admin/dashboard"], ["System Settings"]]}
        title="System Settings"
        icon={<Settings className="h-5 w-5 text-emerald-600" />}
        subtitle="Manage hospital branding and your display preferences."
      />

      {/* ── Branding card ────────────────────────────────────────── */}
      <Card className="p-6">
        <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-2 mb-5">
          <Building2 className="h-4 w-4 text-emerald-600" />
          Hospital Branding
        </h3>

        {brandingLoading && (
          <p className="text-xs text-zinc-400">Loading branding…</p>
        )}

        {brandingError && (
          <p className="text-xs font-semibold text-rose-600">{brandingError}</p>
        )}

        {!brandingLoading && !brandingError && branding && (
          <form onSubmit={handleBrandingSubmit} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Input
                label="Hospital Name"
                value={hospitalName}
                onChange={(e) => setHospitalName(e.target.value)}
                error={validationErrors.hospital_name}
              />
              <Input
                label="Service Name"
                value={serviceName}
                onChange={(e) => setServiceName(e.target.value)}
                error={validationErrors.service_name}
              />
              <Input
                label="Address"
                value={address}
                onChange={(e) => setAddress(e.target.value)}
                error={validationErrors.address}
              />
              <Input
                label="Accreditation"
                value={accreditation}
                onChange={(e) => setAccreditation(e.target.value)}
                error={validationErrors.accreditation}
              />
              <Input
                label="Province"
                value={province}
                onChange={(e) => setProvince(e.target.value)}
                error={validationErrors.province}
              />
              <Input
                label="LGU"
                value={lgu}
                onChange={(e) => setLgu(e.target.value)}
                error={validationErrors.lgu}
              />
            </div>

            {/* Logo uploads */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-zinc-600 tracking-wide">
                  Logo Left
                </label>
                {branding.logo_left_path && (
                  <p className="text-[10px] text-zinc-400 truncate">
                    Current: {branding.logo_left_path}
                  </p>
                )}
                <input
                  ref={logoLeftRef}
                  type="file"
                  accept="image/*"
                  className="text-xs text-zinc-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200"
                />
                {validationErrors.logo_left && (
                  <span className="text-xs font-semibold text-rose-600">
                    {validationErrors.logo_left}
                  </span>
                )}
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-zinc-600 tracking-wide">
                  Logo Right
                </label>
                {branding.logo_right_path && (
                  <p className="text-[10px] text-zinc-400 truncate">
                    Current: {branding.logo_right_path}
                  </p>
                )}
                <input
                  ref={logoRightRef}
                  type="file"
                  accept="image/*"
                  className="text-xs text-zinc-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200"
                />
                {validationErrors.logo_right && (
                  <span className="text-xs font-semibold text-rose-600">
                    {validationErrors.logo_right}
                  </span>
                )}
              </div>
            </div>

            <div className="flex items-center gap-3 pt-2">
              <Button type="submit" loading={saving} className="w-auto">
                Save Branding
              </Button>
              {savedOk && (
                <span className="text-xs font-semibold text-emerald-600">
                  Saved.
                </span>
              )}
              {saveError && (
                <span className="text-xs font-semibold text-rose-600">
                  {saveError}
                </span>
              )}
            </div>
          </form>
        )}
      </Card>

      <PerHeadDayLimitCard />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        {/* ── Appearance card ─────────────────────────────────────── */}
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
            <p className="text-[10px] text-zinc-400">
              Compact tightens spacing and text across the app.
            </p>
          </div>

        </Card>

        {/* ── Notifications card ───────────────────────────────────── */}
        <Card className="p-6 space-y-5">
          <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-2">
            <Bell className="h-4 w-4 text-emerald-600" />
            Notifications
          </h3>
          <label className="flex items-center justify-between gap-3 cursor-pointer">
            <span className="text-xs font-semibold text-zinc-600">New announcements</span>
            <button role="switch" aria-checked={announcementAlerts} onClick={toggleAnnouncements} className={`relative h-5 w-9 rounded-full transition-colors ${announcementAlerts ? "bg-brand-green-600" : "bg-zinc-300"}`}>
              <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-transform ${announcementAlerts ? "translate-x-4" : "translate-x-0.5"}`} />
            </button>
          </label>
          <label className="flex items-center justify-between gap-3 cursor-pointer">
            <span className="text-xs font-semibold text-zinc-600">Follow-up reminders</span>
            <button role="switch" aria-checked={followUpAlerts} onClick={toggleFollowUps} className={`relative h-5 w-9 rounded-full transition-colors ${followUpAlerts ? "bg-brand-green-600" : "bg-zinc-300"}`}>
              <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-transform ${followUpAlerts ? "translate-x-4" : "translate-x-0.5"}`} />
            </button>
          </label>
        </Card>
      </div>
    </div>
  );
}
