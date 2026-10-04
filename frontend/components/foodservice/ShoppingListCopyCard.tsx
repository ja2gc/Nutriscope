"use client";

import { useState } from "react";
import { ChevronDown, Copy } from "lucide-react";
import { Card } from "@/components/ui/Card";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { formatFoodServiceNumber } from "@/lib/foodServiceFormat";
import type { ShoppingListItem } from "@/services/procurementService";

type CopyItem = Pick<ShoppingListItem, "id" | "ingredient_name" | "qty" | "unit">;

export function ShoppingListCopyCard({ items }: { items: CopyItem[] }) {
  const [open, setOpen] = useState(false);
  const [copied, setCopied] = useState(false);
  const [copyError, setCopyError] = useState("");
  const copyText = items
    .map((item) => `${item.ingredient_name}\t${formatFoodServiceNumber(item.qty)} ${item.unit}`)
    .join("\n");

  async function copyList() {
    setCopied(false);
    setCopyError("");
    try {
      await navigator.clipboard.writeText(copyText);
      setCopied(true);
    } catch {
      setCopyError("Copy failed. Select and copy the list text.");
    }
  }

  return (
    <Card className="overflow-hidden p-0">
      <Collapsible open={open} onOpenChange={setOpen}>
        <div className="flex items-center justify-between gap-2 px-3 py-2">
          <CollapsibleTrigger asChild>
            <button type="button" aria-label={open ? "Hide shopping list" : "Show shopping list"}
              className="flex min-h-10 min-w-0 flex-1 cursor-pointer items-center justify-between gap-2 rounded-lg px-2 text-left transition-colors hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500/30">
              <span className="truncate text-xs font-extrabold uppercase tracking-wider text-warm-600">Shopping list <span className="font-semibold text-warm-400">({items.length})</span></span>
              <ChevronDown className={`h-4 w-4 shrink-0 text-warm-400 transition-transform ${open ? "rotate-180" : ""}`} aria-hidden="true" />
            </button>
          </CollapsibleTrigger>
          <button type="button" onClick={copyList} disabled={items.length === 0}
            aria-label="Copy shopping list" className="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border border-warm-200 px-3 text-sm font-semibold text-warm-700 hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-40">
            <Copy className="h-4 w-4" />
            {copied ? "Copied" : "Copy list"}
          </button>
        </div>
        <CollapsibleContent className="border-t border-warm-100 px-3 py-2">
          {items.length > 0 ? (
            <div>
              <div className="mb-1 flex items-center justify-between border-b border-warm-100 pb-1 text-xs font-bold uppercase tracking-wider text-warm-400">
                <span>Item</span><span>Qty</span>
              </div>
              <ul aria-label="Shopping list items" className="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                {items.map((item) => (
                  <li key={item.id} className="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-lg border border-warm-100 px-2 py-1 text-sm leading-5 text-warm-800">
                    <span className="min-w-0 break-words">{item.ingredient_name}</span>
                    <span className="whitespace-nowrap font-mono tabular-nums">{formatFoodServiceNumber(item.qty)} {item.unit}</span>
                  </li>
                ))}
              </ul>
            </div>
          ) : <p className="text-sm text-warm-400">No items to copy yet.</p>}
          {copied && <p role="status" className="sr-only">Shopping list copied.</p>}
          {copyError && <p role="alert" className="mt-1 text-xs font-semibold text-red-600">{copyError}</p>}
        </CollapsibleContent>
      </Collapsible>
    </Card>
  );
}
