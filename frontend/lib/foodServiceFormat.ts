export function formatFoodServiceNumber(value: number | string | null | undefined): string {
  const numeric = typeof value === "string" ? Number(value) : value ?? 0;
  if (!Number.isFinite(numeric)) return "0";
  return new Intl.NumberFormat("en-PH", { maximumFractionDigits: 2, useGrouping: false }).format(numeric);
}
