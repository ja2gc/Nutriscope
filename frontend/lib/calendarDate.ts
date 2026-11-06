// Calendar dates have no timezone. Do arithmetic in UTC so a Manila midnight
// cannot serialize as the previous day.
export function addCalendarDays(start: string, days: number): string {
  const date = new Date(`${start}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

export function manilaCalendarToday(now = new Date()): string {
  const parts = new Intl.DateTimeFormat("en-US", {
    timeZone: "Asia/Manila", year: "numeric", month: "2-digit", day: "2-digit",
  }).formatToParts(now);
  const value = (type: string) => parts.find((part) => part.type === type)?.value ?? "";
  return `${value("year")}-${value("month")}-${value("day")}`;
}

export function manilaRelativeDate(value?: string | null, now = new Date()): string {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const days = Math.round((Date.parse(manilaCalendarToday(date)) - Date.parse(manilaCalendarToday(now))) / 86400000);
  if (days === 0) return "Today";
  if (days > 0) return days === 1 ? "In 1 day" : `In ${days} days`;
  return days === -1 ? "1 day ago" : `${-days} days ago`;
}
