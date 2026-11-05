import type { AnnouncementRole } from "@/services/announcementService";

const roles: AnnouncementRole[] = ["RND", "FSS", "Admin"];

export function AnnouncementAudienceSelect({ value, onChange }: {
  value: AnnouncementRole[];
  onChange: (roles: AnnouncementRole[]) => void;
}) {
  return (
    <fieldset className="min-w-0 space-y-1.5">
      <legend className="text-xs font-semibold text-warm-500 uppercase tracking-wider">Visible to</legend>
      <p className="text-xs text-warm-500">Select at least one role.</p>
      <div className="flex flex-wrap gap-x-4 gap-y-1">
        {roles.map((role) => (
          <label key={role} className="flex min-h-11 items-center gap-2 text-sm text-warm-700">
            <input type="checkbox" checked={value.includes(role)}
              disabled={value.length === 1 && value.includes(role)}
              onChange={(event) => onChange(event.target.checked ? roles.filter((item) => item === role || value.includes(item)) : value.filter((item) => item !== role))}
              className="h-4 w-4 accent-emerald-700" />
            {role}
          </label>
        ))}
      </div>
    </fieldset>
  );
}
