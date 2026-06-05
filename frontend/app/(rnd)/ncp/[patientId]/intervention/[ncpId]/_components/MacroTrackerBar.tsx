interface MacroTarget { label: string; current: number; target: number; unit: string }

interface Props { targets: MacroTarget[]; className?: string }

function statusColor(current: number, target: number): string {
  if (target <= 0) return "text-zinc-400";
  const pct = Math.abs(current - target) / target;
  if (pct <= 0.10) return "text-emerald-700";
  if (pct <= 0.20) return "text-amber-600";
  return "text-red-600";
}

export default function MacroTrackerBar({ targets, className = '' }: Props) {
  if (targets.every((t) => t.target <= 0)) return null;
  return (
    <div className={`flex flex-wrap items-center gap-x-5 gap-y-1 px-4 py-2.5 bg-emerald-50 border-b border-emerald-100 ${className}`}>
      {targets.map(({ label, current, target, unit }) => (
        <div key={label} className="flex items-baseline gap-1">
          <span className="text-[9px] font-bold text-emerald-600 uppercase tracking-wider">{label}</span>
          <span className={`text-sm font-extrabold font-mono ${statusColor(current, target)}`}>
            {Math.round(current)}
          </span>
          <span className="text-[9px] text-zinc-400">/ {Math.round(target)} {unit}</span>
        </div>
      ))}
    </div>
  );
}
