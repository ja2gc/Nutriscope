"use client";

import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import type { InterventionPlanSummary } from "@/services/interventionService";
import { useState } from 'react';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';

interface Props {
  plans: InterventionPlanSummary[];
  meta: PaginationMeta | null;
  page: number;
  selectedPlanId: string | null;
  latestPlanId: string | null;
  loading: boolean;
  error: string | null;
  onCreate: () => void;
  onSelect: (planId: string) => void;
  onDelete: (planId: string) => Promise<void>;
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
  latestPlanId,
  loading,
  error,
  onCreate,
  onSelect,
  onDelete,
  onPageChange,
}: Props) {
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  return (
    <section aria-labelledby="intervention-plans-heading" className="min-w-0 overflow-hidden rounded-none border-0 bg-white sm:rounded-xl sm:border sm:border-warm-200">
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
          <div className="hidden grid-cols-[10rem_1fr_auto] gap-4 bg-warm-50 px-4 py-2 text-xs font-bold text-warm-500 sm:grid"><span>Date</span><span>Goal</span><span>Actions</span></div>
          {plans.map((plan) => (
            <div
              key={plan.id}
              aria-current={selectedPlanId === plan.id ? "true" : undefined}
              className={`grid w-full grid-cols-1 gap-1 px-4 py-3 text-left sm:grid-cols-[10rem_1fr_auto] sm:items-center sm:gap-4 ${
                selectedPlanId === plan.id ? "bg-warm-50" : "bg-white hover:bg-warm-50/60"
              }`}
            >
              <time dateTime={plan.plan_date} className="text-sm font-semibold text-warm-800">
                {planDate.format(new Date(plan.plan_date))}
              </time>
              <span className="text-sm capitalize text-warm-600">{goalSummary(plan)}</span>
              <div aria-label="Actions" className="flex items-center justify-end gap-2">
                {plan.id !== latestPlanId && <span className="rounded-full border border-warm-200 px-3 py-1 text-xs font-bold text-warm-500">View only</span>}
                <Button variant="icon" aria-label={`${plan.id === latestPlanId ? 'Edit' : 'View'} plan ${planDate.format(new Date(plan.plan_date))}`} title={plan.id === latestPlanId ? 'Edit' : 'View'} onClick={() => onSelect(plan.id)}>{plan.id === latestPlanId ? <Pencil className="h-4 w-4" /> : <Eye className="h-4 w-4" />}</Button>
                <Button variant="icon" aria-label={`Delete plan ${planDate.format(new Date(plan.plan_date))}`} title="Delete" onClick={() => { setDeletingId(plan.id); setDeleteError(null); }} className="hover:text-red-600"><Trash2 className="h-4 w-4" /></Button>
              </div>
            </div>
          ))}
        </div>
      )}

      <Pagination meta={meta} page={page} onPageChange={onPageChange} />
      {deletingId && <div role="dialog" aria-modal="true" aria-label="Delete Intervention Plan" className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
        <div className="w-full max-w-sm space-y-4 rounded-2xl bg-white p-6 shadow-2xl">
          <h3 className="text-base font-extrabold text-warm-900">Delete Intervention Plan?</h3>
          <p className="text-sm text-warm-500">This will permanently delete this plan and its weekly menu.</p>
          {deleteError && <p role="alert" className="text-sm text-red-700">{deleteError}</p>}
          <div className="flex justify-end gap-2"><Button variant="ghost" disabled={deleting} onClick={() => setDeletingId(null)}>Cancel</Button><Button variant="danger" loading={deleting} onClick={async () => { setDeleting(true); try { await onDelete(deletingId); setDeletingId(null); } catch (error) { setDeleteError(error instanceof Error ? error.message : 'Could not delete plan.'); } finally { setDeleting(false); } }}>Delete Plan</Button></div>
        </div>
      </div>}
    </section>
  );
}
