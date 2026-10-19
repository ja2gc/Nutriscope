export function formatFoodServiceNumber(value: number | string | null | undefined): string {
  const numeric = typeof value === "string" ? Number(value) : value ?? 0;
  if (!Number.isFinite(numeric)) return "0";
  return new Intl.NumberFormat("en-PH", { maximumFractionDigits: 2, useGrouping: false }).format(numeric);
}

// Format only the field value; callers retain the original quantity for calculations and saving.
export function quantityInputValue(value: number | string | null | undefined): string {
  if (value == null || value === '') return '';
  const text = String(value);
  return /^-?\d*(?:\.\d{0,2})?$/.test(text) ? text : formatFoodServiceNumber(value);
}
