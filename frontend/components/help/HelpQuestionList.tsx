"use client";

import { useState } from "react";
import { ChevronDown } from "lucide-react";
import type { HelpItem } from "@/lib/helpContent";

export function HelpQuestionList({
  items,
  instanceId,
}: {
  items: HelpItem[];
  instanceId: string;
}) {
  const [expandedIds, setExpandedIds] = useState<Set<string>>(new Set());

  const toggle = (id: string) => {
    setExpandedIds((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  return (
    <div className="divide-y divide-warm-100">
      {items.map((item) => {
        const expanded = expandedIds.has(item.id);
        const controlId = `${instanceId}-${item.id}-control`;
        const panelId = `${instanceId}-${item.id}-panel`;

        return (
          <div key={item.id}>
            <h3>
              <button
                id={controlId}
                type="button"
                aria-expanded={expanded}
                aria-controls={panelId}
                onClick={() => toggle(item.id)}
                className="flex min-h-12 w-full cursor-pointer items-center justify-between gap-4 px-4 py-3.5 text-left text-base font-semibold text-warm-800 transition-colors hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-green-500/40 sm:px-5"
              >
                <span>{item.question}</span>
                <ChevronDown
                  aria-hidden="true"
                  className={`h-5 w-5 shrink-0 text-brand-green-700 transition-transform duration-200 motion-reduce:transition-none ${
                    expanded ? "rotate-180" : ""
                  }`}
                />
              </button>
            </h3>
            {expanded && (
              <div
                id={panelId}
                role="region"
                aria-labelledby={controlId}
                className="px-4 pb-4 pr-12 text-base leading-7 text-warm-600 sm:px-5 sm:pr-16"
              >
                {item.answer}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
