"use client";

import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import type { InterventionPlanSummary } from "@/services/interventionService";

interface Props {
  plans: InterventionPlanSummary[];
  meta: PaginationMeta | null;
  page: number;
  selectedPlanId: string | null;
  loading: boolean;
  error: string | null;
  onCreate: () => void;
  onSelect: (planId: string) => void;
  onPageChange: (page: number) => void;
}

const planDate = new Intl.DateTimeFormat("en-US", {
  month: "short",
  day: "numeric",
  year: "numeric",
  timeZone: "Asia/Manila",
});

function goalSummary(plan: InterventionPlanSummary): string {
  const goal = plan.goal_type?.replaceAll("_", " ") ?? "Goal not recorded";
  const stage = plan.disease_stage?.replaceAll("_", " ");

  return stage ? `${goal} · ${stage}` : goal;
}

export default function InterventionPlansTab({
  plans,
  meta,
  page,
  selectedPlanId,
  loading,
  error,
  onCreate,
  onSelect,
  onPageChange,
}: Props) {
  return (
    <section aria-labelledby="intervention-plans-heading" className="border border-warm-200 rounded-xl overflow-hidden">
      <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-warm-200">
        <h3 id="intervention-plans-heading" className="text-sm font-bold text-warm-800">Plans</h3>
        <button
          type="button"
          onClick={onCreate}
          className="px-3 py-2 text-sm font-bold text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg"
        >
          Create New Intervention Plan
        </button>
      </div>

      {error && <p role="alert" className="px-4 py-3 text-sm text-red-700">{error}</p>}
      {loading && <p className="px-4 py-5 text-sm text-warm-500">Loading plans…</p>}
      {!loading && plans.length === 0 && <p className="px-4 py-5 text-sm text-warm-500">No saved plans.</p>}

      {!loading && plans.length > 0 && (
        <div className="divide-y divide-warm-100">
          {plans.map((plan) => (
            <button
              key={plan.id}
              type="button"
              onClick={() => onSelect(plan.id)}
              aria-current={selectedPlanId === plan.id ? "true" : undefined}
              className={`grid w-full grid-cols-1 gap-1 px-4 py-3 text-left sm:grid-cols-[10rem_1fr_auto] sm:items-center sm:gap-4 ${
                selectedPlanId === plan.id ? "bg-warm-50" : "hover:bg-warm-50/60"
              }`}
            >
              <time dateTime={plan.plan_date} className="text-sm font-semibold text-warm-800">
                {planDate.format(new Date(plan.plan_date))}
              </time>
              <span className="text-sm capitalize text-warm-600">{goalSummary(plan)}</span>
              <span className="text-sm font-semibold text-emerald-700">View</span>
            </button>
          ))}
        </div>
      )}

      <Pagination meta={meta} page={page} onPageChange={onPageChange} />
    </section>
  );
}
