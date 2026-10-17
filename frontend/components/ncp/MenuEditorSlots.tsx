import { Children, isValidElement, type MouseEvent, type ReactNode } from "react";
import { Plus } from "lucide-react";

export function MenuEditorSlots({ items, addLabel, addAriaLabel, onAdd, readOnly = false, emptyLabel = "—" }: {
  items: ReactNode[];
  addLabel: string;
  addAriaLabel?: string;
  onAdd?: (event: MouseEvent<HTMLButtonElement>) => void;
  readOnly?: boolean;
  emptyLabel?: string;
}) {
  return (
    <div className="grid gap-2" data-menu-editor-slots>
      {Children.toArray(items).map((item, index) => (
        <div key={isValidElement(item) ? item.key : index} data-menu-editor-item className="h-32 min-w-0 overflow-hidden rounded-lg border border-warm-200 bg-white p-2">
          {item}
        </div>
      ))}
      {!readOnly && onAdd ? (
        <button type="button" data-menu-editor-add aria-label={addAriaLabel ?? addLabel} onClick={onAdd}
          className="flex h-32 w-full items-center justify-center gap-1 rounded-lg border border-dashed border-warm-300 px-2 text-xs font-semibold text-warm-500 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">
          <Plus className="h-3.5 w-3.5" />{addLabel}
        </button>
      ) : items.length === 0 ? (
        <div data-menu-editor-empty className="flex h-32 items-center justify-center rounded-lg border border-warm-100 bg-warm-50/50 text-xs text-warm-300">{emptyLabel}</div>
      ) : null}
    </div>
  );
}
