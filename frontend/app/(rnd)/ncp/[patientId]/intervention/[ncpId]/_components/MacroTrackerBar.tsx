import { FlaskConical } from "lucide-react";
import type { ReactNode } from "react";

interface MacroTarget { label: string; current: number | null; target: number; unit: string; operator?: 'min' | 'max' }

interface Props {
  targets: MacroTarget[];
  showMicros?: boolean;
  onToggleMicros?: () => void;
  hasMicros?: boolean;
  className?: string;
  showWithoutTargets?: boolean;
  children?: ReactNode;
}

function statusColor(current: number, target: number, operator?: 'min' | 'max'): string {
  if (operator) return (operator === 'max' ? current <= target : current >= target) ? "text-emerald-700" : "text-red-600";
  if (target <= 0) return "text-warm-400";
  const pct = Math.abs(current - target) / target;
  if (pct <= 0.10) return "text-emerald-700";
  if (pct <= 0.20) return "text-amber-600";
  return "text-red-600";
}

export default function MacroTrackerBar({
  targets,
  showMicros = false,
  onToggleMicros,
  hasMicros = false,
  className = "",
  showWithoutTargets = false,
  children,
}: Props) {
  if (!showWithoutTargets && targets.every((target) => target.target <= 0)) return null;
  return (
    <div className={`flex flex-wrap items-center gap-x-5 gap-y-2 px-4 py-3 bg-white border border-warm-200 rounded-xl ${className}`}>
      <div className="flex flex-wrap items-center gap-x-5 gap-y-1 flex-1 min-w-0">
        {targets.map(({ label, current, target, unit, operator }) => (
          <div key={label} className="flex items-baseline gap-1">
            <span className="text-xs font-bold text-warm-400 uppercase tracking-wider">{label}</span>
            <span className={`text-base font-extrabold font-mono ${current === null ? 'text-warm-500' : label === 'Fluid' || (showWithoutTargets && target <= 0) ? 'text-warm-900' : statusColor(current, target, operator)}`}>
              {current === null ? 'Not recorded' : Math.round(current)}
            </span>
            <span className="text-xs text-warm-500">{target > 0 && <>/ {operator === 'max' ? '≤' : operator === 'min' ? '≥' : ''}{Math.round(target)} </>}{unit}</span>
          </div>
        ))}
      </div>
      {hasMicros && onToggleMicros && (
        <button
          onClick={onToggleMicros}
          className={`flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold border rounded-lg transition-colors cursor-pointer flex-shrink-0 ${
            showMicros
              ? "bg-sky-600 text-white border-sky-600"
              : "border-warm-200 text-warm-500 hover:border-sky-400 hover:text-sky-700"
          }`}
        >
          <FlaskConical className="h-3 w-3" /> Micros
        </button>
      )}
      {children && <div className="w-full border-t border-warm-100 pt-2">{children}</div>}
    </div>
  );
}
