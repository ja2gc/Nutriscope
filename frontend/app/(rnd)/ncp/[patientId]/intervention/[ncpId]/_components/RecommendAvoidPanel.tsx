import { ThumbsUp, ThumbsDown, AlertCircle } from "lucide-react";
import { RecommendResult } from "@/services/interventionService";

interface Props { data: RecommendResult | null; loading: boolean }

export default function RecommendAvoidPanel({ data, loading }: Props) {
  if (loading) return (
    <div className="h-20 flex items-center justify-center text-xs text-zinc-400">
      Loading recommendations…
    </div>
  );
  if (!data) return null;

  const { recommend, avoid, limits } = data;
  const hasContent = recommend.length > 0 || avoid.length > 0 || limits.length > 0;
  if (!hasContent) return (
    <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl text-xs text-zinc-400 text-center">
      No specific dietary restrictions for this goal. Clinical rules will apply to individual food items.
    </div>
  );

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
      {/* Recommend */}
      <div className="space-y-2">
        <p className="flex items-center gap-1.5 text-[9px] font-bold text-emerald-600 uppercase tracking-widest">
          <ThumbsUp className="h-3 w-3" /> Recommend
        </p>
        {recommend.length === 0
          ? <p className="text-[10px] text-zinc-300 italic">No specific recommendations.</p>
          : recommend.map((r, i) => (
            <div key={i} className="flex items-start gap-2 p-2.5 border-l-2 border-emerald-400 bg-emerald-50 rounded-r-xl">
              <div>
                <p className="text-xs font-semibold text-zinc-800">{r.tag}</p>
                <p className="text-[10px] text-zinc-500">{r.reason}</p>
              </div>
            </div>
          ))}
      </div>

      {/* Avoid */}
      <div className="space-y-2">
        <p className="flex items-center gap-1.5 text-[9px] font-bold text-red-500 uppercase tracking-widest">
          <ThumbsDown className="h-3 w-3" /> Avoid
        </p>
        {avoid.length === 0
          ? <p className="text-[10px] text-zinc-300 italic">No specific restrictions.</p>
          : avoid.map((r, i) => (
            <div key={i} className="flex items-start gap-2 p-2.5 border-l-2 border-red-400 bg-red-50 rounded-r-xl">
              <div>
                <p className="text-xs font-semibold text-zinc-800">{r.tag}</p>
                <p className="text-[10px] text-zinc-500">{r.reason}</p>
              </div>
            </div>
          ))}
      </div>

      {/* Limits */}
      {limits.length > 0 && (
        <div className="md:col-span-2 space-y-2">
          <p className="flex items-center gap-1.5 text-[9px] font-bold text-amber-600 uppercase tracking-widest">
            <AlertCircle className="h-3 w-3" /> Limits
          </p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {limits.map((r, i) => (
              <div key={i} className="flex items-start gap-2 p-2.5 border-l-2 border-amber-400 bg-amber-50 rounded-r-xl">
                <div>
                  <p className="text-xs font-semibold text-zinc-800">{r.tag}
                    <span className="ml-1 text-[9px] font-normal text-zinc-500">≤ {r.threshold} {r.unit}</span>
                  </p>
                  <p className="text-[10px] text-zinc-500">{r.reason}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
