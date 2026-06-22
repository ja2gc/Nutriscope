// Patient-specific Monitoring Plan — mirrors the backend MonitoringPlanService
// output. Single source of truth for the visit form, trend charts, and tracker.

export type IndicatorCategory = "lab" | "anthro" | "intake";
export type IndicatorStatus = "met" | "in_progress" | "not_met" | "no_data";

export interface PlanVisit {
  label: string;
  type: "assessment" | "monitoring";
  id?: number;
  date: string | null;
}

export interface PlanSeriesPoint {
  visit: string;
  value: number | null;
  status: IndicatorStatus;
}

export interface PlanIndicator {
  key: string;
  label: string;
  unit: string;
  category: IndicatorCategory;
  sources: string[];
  reference: { min?: number | null; max?: number | null; lowerIsBetter?: boolean } | null;
  target: number | null;
  series: PlanSeriesPoint[];
  latest_status: IndicatorStatus;
}

export interface MonitoringPlan {
  visits: PlanVisit[];
  pes_statements: string[];
  goal_type: string | null;
  nutritional_status: string | null;
  indicators: PlanIndicator[];
}

/** Flatten an indicator's series to chart points (visit label + value, nulls preserved). */
export function planToChartSeries(indicator: PlanIndicator): Array<{ visit: string; value: number | null }> {
  return indicator.series.map((p) => ({ visit: p.visit, value: p.value }));
}
