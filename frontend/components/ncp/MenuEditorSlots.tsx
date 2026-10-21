import { Children, isValidElement, type MouseEvent, type ReactNode } from "react";
import { Plus, Pencil, X } from "lucide-react";

export function MenuEditorActions({ children }: { children: ReactNode }) {
  return <div data-menu-editor-actions className="pointer-events-auto absolute right-1 top-1 z-10 flex shrink-0 justify-end gap-0.5">{children}</div>;
}

export function MenuEditorAction({ kind, label, onClick, disabled = false }: {
  kind: "view" | "remove";
  label: string;
  onClick: (event: MouseEvent<HTMLButtonElement>) => void;
  disabled?: boolean;
}) {
  const Icon = kind === "remove" ? X : Pencil;
  return <button type="button" data-menu-editor-action={kind} aria-label={label} title={kind === "remove" ? "Remove" : label.startsWith("View") ? "View" : "Edit"} onClick={onClick} disabled={disabled}
    className={`flex h-6 w-6 shrink-0 items-center justify-center rounded p-0 text-warm-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 disabled:opacity-50 ${kind === "remove" ? "hover:bg-red-50 hover:text-red-600" : "hover:bg-emerald-50 hover:text-emerald-700"}`}>
    <Icon className="h-3.5 w-3.5" />
  </button>;
}

export function MenuEditorSlots({ items, itemActions, addLabel, addAriaLabel, onAdd, readOnly = false, emptyLabel = "—", compact = false }: {
  items: ReactNode[];
  itemActions?: Array<{ id?: string; label: string; onOpen: (trigger: HTMLButtonElement) => void } | undefined>;
  addLabel: string;
  addAriaLabel?: string;
  onAdd?: (event: MouseEvent<HTMLButtonElement>) => void;
  readOnly?: boolean;
  emptyLabel?: string;
  compact?: boolean;
}) {
  return (
    <div className="grid gap-2" data-menu-editor-slots>
      {Children.toArray(items).map((item, index) => (
        <div key={isValidElement(item) ? item.key : index} data-menu-editor-item className={`relative min-w-0 overflow-hidden rounded-lg border border-warm-200 bg-white p-2 ${compact ? 'min-h-16 text-left' : 'h-32 text-center [&>div]:justify-center [&>div]:py-5'} ${itemActions?.[index] ? '[&>div]:pointer-events-none hover:border-emerald-300 hover:bg-emerald-50/30' : ''}`}>
          {itemActions?.[index] && <button type="button" data-menu-editor-open id={itemActions[index]?.id} aria-label={itemActions[index]?.label} title={itemActions[index]?.label.startsWith('Edit') ? 'Edit' : 'View'} onClick={(event) => itemActions[index]?.onOpen(event.currentTarget)} className="absolute inset-0 h-full w-full cursor-pointer rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600" />}
          {item}
        </div>
      ))}
      {!readOnly && onAdd ? (
        <button type="button" data-menu-editor-add aria-label={addAriaLabel ?? addLabel} onClick={onAdd}
          className="flex min-h-10 w-full items-center justify-center gap-1 rounded-lg border border-dashed border-warm-300 px-2 py-2 text-xs font-semibold text-warm-500 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">
          <Plus className="h-3.5 w-3.5" />{addLabel}
        </button>
      ) : items.length === 0 ? (
        <div data-menu-editor-empty className="flex h-32 items-center justify-center rounded-lg border border-warm-100 bg-warm-50/50 text-xs text-warm-300">{emptyLabel}</div>
      ) : null}
    </div>
  );
}
