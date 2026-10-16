"use client";

import type { BackupScheduleInput, BackupSchedulesDto } from "@/types/backup";
import { Card } from "@/components/ui/Card";

const options = [
  ["daily", "Daily backups", "Runs every day.", "Expires after 4 days."],
  ["weekly", "Weekly backups", "Runs every Sunday.", "Expires after 14 days."],
  ["monthly", "Monthly backups", "Runs on the first of each month.", "Expires after 3 calendar months."],
] as const;

export function BackupScheduleSettings({ schedules, disabled, onChange }: {
  schedules: BackupSchedulesDto;
  disabled: boolean;
  onChange: (input: BackupScheduleInput) => void;
}) {
  const values = {
    daily: schedules.daily.enabled,
    weekly: schedules.weekly.enabled,
    monthly: schedules.monthly.enabled,
  };

  return (
    <Card padded>
      <div className="mb-4"><h2 className="text-base font-extrabold text-warm-900">Automatic backup schedules</h2><p className="mt-1 text-sm text-warm-600">Enabled schedules run at 1:30 AM Asia/Manila. Expiration applies to backup copies, not application records.</p></div>
      <div className="grid gap-3 lg:grid-cols-3">
        {options.map(([key, label, description, expiration]) => {
          const enabled = schedules[key].enabled;
          return <div key={key} className="rounded-xl border border-warm-200 p-4"><div className="flex items-start justify-between gap-3"><div><p className="text-sm font-bold text-warm-900">{label}</p><p className="mt-1 text-xs leading-relaxed text-warm-500">{description}</p><p className="mt-2 text-sm font-semibold text-warm-700">{expiration}</p></div><button type="button" role="switch" aria-checked={enabled} aria-label={label} disabled={disabled} onClick={() => onChange({ ...values, [key]: !enabled })} className={`relative h-7 w-12 shrink-0 rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500 disabled:opacity-50 ${enabled ? "bg-brand-green-600" : "bg-warm-300"}`}><span className={`absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-transform ${enabled ? "left-6" : "left-1"}`} /></button></div>{enabled && schedules[key].next_at && <p className="mt-3 text-xs font-semibold text-brand-green-700">Next: {new Intl.DateTimeFormat("en-PH", { dateStyle: "medium", timeStyle: "short", timeZone: "Asia/Manila" }).format(new Date(schedules[key].next_at))}</p>}</div>;
        })}
      </div>
      <p className="mt-4 text-xs leading-relaxed text-warm-600">One automatic backup can cover several schedules and stays available until its last expiration date. After it expires, it moves to Recently deleted for 7 days before permanent deletion. The newest verified backup and backups involved in an active restoration stay protected. Deleting an older backup does not create a replacement.</p>
    </Card>
  );
}
