import type { AuditValueDto } from "@/types/audit";

const numberFormatter = new Intl.NumberFormat("en-PH", { maximumFractionDigits: 2 });
const dateFormatter = new Intl.DateTimeFormat("en-PH", { dateStyle: "medium", timeZone: "Asia/Manila" });
const dateTimeFormatter = new Intl.DateTimeFormat("en-PH", {
  dateStyle: "medium",
  timeStyle: "short",
  timeZone: "Asia/Manila",
});

function tokenLabel(value: string) {
  const normalized = value.replaceAll(/[_-]+/g, " ").trim();
  return normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : value;
}

function unsupported() {
  return <span className="text-warm-500" role="note">Unsupported value</span>;
}

export function AuditValue({ value }: { value: AuditValueDto }) {
  if (value.type === "redacted") return <span className="text-warm-600">Value hidden</span>;
  if (value.value === null) return <span className="text-warm-500">Not recorded</span>;

  if (Array.isArray(value.value)) {
    if (value.type !== "field_list" || value.value.some((item) => typeof item !== "string")) return unsupported();
    return <span>{value.value.map(tokenLabel).join(", ")}</span>;
  }
  if (typeof value.value === "object") return unsupported();

  if (value.type === "boolean") {
    return typeof value.value === "boolean" ? <span>{value.value ? "Yes" : "No"}</span> : unsupported();
  }
  if (value.type === "number") {
    return typeof value.value === "number" && Number.isFinite(value.value)
      ? <span>{numberFormatter.format(value.value)}</span>
      : unsupported();
  }
  if (value.type === "currency") {
    if (typeof value.value !== "number" || !Number.isFinite(value.value)) return unsupported();
    const currency = value.currency && /^[A-Z]{3}$/.test(value.currency) ? value.currency : "PHP";
    return <span>{new Intl.NumberFormat("en-PH", { style: "currency", currency }).format(value.value)}</span>;
  }
  if (value.type === "quantity") {
    if (typeof value.value !== "number" || !Number.isFinite(value.value)) return unsupported();
    return <span>{numberFormatter.format(value.value)}{value.unit ? ` ${value.unit}` : ""}</span>;
  }
  if (value.type === "date" || value.type === "datetime") {
    if (typeof value.value !== "string") return unsupported();
    const parsed = value.type === "date" && /^\d{4}-\d{2}-\d{2}$/.test(value.value)
      ? new Date(`${value.value}T00:00:00+08:00`)
      : new Date(value.value);
    if (Number.isNaN(parsed.getTime())) return unsupported();
    return <span>{value.type === "date" ? dateFormatter.format(parsed) : dateTimeFormatter.format(parsed)}</span>;
  }
  if (value.type === "enum") {
    return typeof value.value === "string" ? <span>{tokenLabel(value.value)}</span> : unsupported();
  }
  if (value.type === "text" || value.type === "reference") {
    return typeof value.value === "string" ? <span className="break-words">{value.value}</span> : unsupported();
  }

  return unsupported();
}
