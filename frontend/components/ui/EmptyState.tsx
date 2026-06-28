import React from "react";
import { Card } from "./Card";

/** Friendly empty state: icon + message + optional action. (ux: empty-states) */
export function EmptyState({
  icon,
  title,
  message,
  action,
  className = "",
}: {
  icon?: React.ReactNode;
  title?: string;
  message: React.ReactNode;
  action?: React.ReactNode;
  className?: string;
}) {
  return (
    <Card className={`p-12 text-center max-w-xl mx-auto ${className}`}>
      {icon && (
        <div className="p-3.5 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-brand-green-600">
          {icon}
        </div>
      )}
      {title && <h3 className="text-sm font-bold text-warm-800 mt-4 uppercase tracking-wider">{title}</h3>}
      <p className="text-xs text-warm-500 mt-2 leading-relaxed">{message}</p>
      {action && <div className="mt-4 flex justify-center">{action}</div>}
    </Card>
  );
}
