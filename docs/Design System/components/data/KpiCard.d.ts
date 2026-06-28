import * as React from "react";

export interface KpiCardProps {
  label: string;
  value: React.ReactNode;
  hint?: string;
  tone?: "neutral" | "emerald" | "amber" | "red" | "sky";
  icon?: React.ReactNode;
  style?: React.CSSProperties;
}

/**
 * Metric tile — uppercase label + big tabular value, tinted by tone.
 *
 * @startingPoint section="Data" subtitle="KPI metric tile with tone tint" viewport="700x150"
 */
export function KpiCard(props: KpiCardProps): React.JSX.Element;
