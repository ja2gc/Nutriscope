"use client";

import Link from "next/link";
import { useState } from "react";
import { Eye, History } from "lucide-react";
import { AuditTimestamp } from "@/components/audit/AuditTimestamp";
import { StructuredHistorySnapshot } from "@/components/audit/history/StructuredHistorySnapshot";
import { BudgetHistory } from "@/components/audit/history/types/BudgetHistory";
import { FoodServiceRecipeHistory } from "@/components/audit/history/types/FoodServiceRecipeHistory";
import { MenuCycleHistory } from "@/components/audit/history/types/MenuCycleHistory";
import { PurchaseOrderHistory } from "@/components/audit/history/types/PurchaseOrderHistory";
import { RndRecipeHistory } from "@/components/audit/history/types/RndRecipeHistory";
import { ShoppingListHistory } from "@/components/audit/history/types/ShoppingListHistory";
import { Badge } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";
import type { AuditHistoryDto, AuditHistorySnapshotDto } from "@/types/auditHistory";

function HistorySnapshot({
  snapshot,
  comparison,
  side,
}: {
  snapshot: AuditHistorySnapshotDto;
  comparison: AuditHistorySnapshotDto | null;
  side: "before" | "after";
}) {
  switch (snapshot.type) {
    case "budget":
      return <BudgetHistory snapshot={snapshot} comparison={comparison} side={side} />;
    case "rnd_recipe":
      return <RndRecipeHistory snapshot={snapshot} comparison={comparison} side={side} />;
    case "food_service_recipe":
      return <FoodServiceRecipeHistory snapshot={snapshot} comparison={comparison} side={side} />;
    case "menu_cycle":
    case "menu_cycle_template":
      return <MenuCycleHistory snapshot={snapshot} comparison={comparison} side={side} />;
    case "purchase_order":
      return <PurchaseOrderHistory snapshot={snapshot} comparison={comparison} side={side} />;
    case "shopping_list":
      return <ShoppingListHistory snapshot={snapshot} comparison={comparison} side={side} />;
    default:
      return <StructuredHistorySnapshot snapshot={snapshot} />;
  }
}

export function AuditHistoryView({ history }: { history: AuditHistoryDto }) {
  const initialSide = history.after ? "after" : "before";
  const [side, setSide] = useState<"before" | "after">(initialSide);
  const selected = side === "before" ? history.before : history.after;
  const comparison = side === "before" ? history.after : history.before;
  const hasToggle = history.before !== null && history.after !== null;

  return (
    <div className="space-y-5">
      <Card padded className="border-brand-green-200 bg-brand-green-50/40">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div className="flex items-center gap-2 text-brand-green-700">
              <History className="h-5 w-5" aria-hidden="true" />
              <p className="text-xs font-bold uppercase tracking-wider">Read-only historical version</p>
            </div>
            <p className="mt-2 text-sm text-warm-700">This is the record as captured when the audited event occurred.</p>
            <p className="mt-2 text-xs text-warm-500"><AuditTimestamp value={history.version.occurred_at} /></p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone="zinc">Schema {history.version.schema_version}</Badge>
            <Badge tone="violet">{history.event.action_label}</Badge>
          </div>
        </div>
      </Card>

      <Card padded>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-warm-500">Event</p>
            <p className="mt-1 text-sm font-semibold text-warm-800 break-words">{history.event.summary}</p>
          </div>
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-warm-500">Actor</p>
            <p className="mt-1 text-sm font-semibold text-warm-800 break-words">{history.event.actor?.name || "System actor"}</p>
          </div>
        </div>
        {history.event.reason && <p className="mt-4 rounded-xl bg-warm-50 p-3 text-sm text-warm-700"><strong>Reason:</strong> {history.event.reason}</p>}
      </Card>

      <Card padded>
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2">
            <Eye className="h-5 w-5 text-warm-500" aria-hidden="true" />
            <h2 className="text-sm font-extrabold text-warm-900">Captured record</h2>
          </div>
          {hasToggle && (
            <div className="inline-flex rounded-xl border border-warm-200 bg-warm-50 p-1" aria-label="Historical version side">
              {(["before", "after"] as const).map((value) => (
                <button
                  key={value}
                  type="button"
                  aria-pressed={side === value}
                  onClick={() => setSide(value)}
                  className={`min-h-11 rounded-lg px-4 text-sm font-bold capitalize transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30 ${side === value ? "bg-white text-brand-green-700 shadow-sm" : "text-warm-600 hover:text-warm-900"}`}
                >
                  {value === "before" ? "Before" : "After"}
                </button>
              ))}
            </div>
          )}
        </div>
        {selected
          ? <HistorySnapshot snapshot={selected} comparison={comparison} side={side} />
          : <p className="text-sm text-warm-500">No captured version is available.</p>}
      </Card>

      {history.event.current_record_url && (
        <Link href={history.event.current_record_url} className="inline-flex min-h-11 items-center font-bold text-brand-green-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30">
          View current record
        </Link>
      )}
    </div>
  );
}
