import React from "react";
import Link from "next/link";

/** Breadcrumb segments: [label, href?]. Last is the current page (bold, no link). */
export type Crumb = [label: string, href?: string];

/**
 * Shared page header: breadcrumb trail + icon title + subtitle + optional right
 * actions, with a bottom rule. The same masthead every Food Service / NCP /
 * Reports page uses, so headers stop drifting per page.
 */
export function PageHeader({
  crumbs,
  title,
  icon,
  subtitle,
  actions,
}: {
  crumbs: Crumb[];
  title: string;
  icon?: React.ReactNode;
  subtitle?: string;
  actions?: React.ReactNode;
}) {
  return (
    <div className="space-y-4">
      <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        {crumbs.map(([label, href], i) => (
          <React.Fragment key={label}>
            {i > 0 && <span className="text-zinc-300">/</span>}
            {href ? (
              <Link href={href} className="hover:text-brand-green-700 transition-colors">{label}</Link>
            ) : (
              <span className="font-bold text-zinc-600">{label}</span>
            )}
          </React.Fragment>
        ))}
      </nav>
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h1 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            {icon}
            {title}
          </h1>
          {subtitle && <p className="text-xs text-zinc-500 mt-1 max-w-2xl">{subtitle}</p>}
        </div>
        {actions && <div className="flex items-center gap-2 shrink-0">{actions}</div>}
      </div>
    </div>
  );
}
