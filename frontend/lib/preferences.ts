// Local-only UX preferences (rnd.md §8) — persisted in localStorage, no backend.
// Applied as attributes/classes on <html> so they cascade app-wide:
//  - density: scales the root rem (Tailwind spacing/text are rem-based).
//  - notification toggles: controls user-facing notification categories.

export type Density = "comfortable" | "compact";

const DENSITY_KEY = "ns_density";
const ANNOUNCEMENTS_KEY = "ns_notify_announcements";
const FOLLOW_UPS_KEY = "ns_notify_follow_ups";

export function getDensity(): Density {
  if (typeof window === "undefined") return "comfortable";
  return localStorage.getItem(DENSITY_KEY) === "compact" ? "compact" : "comfortable";
}

export function applyPreferences(
  density: Density = getDensity(),
): void {
  if (typeof document === "undefined") return;
  const root = document.documentElement;
  root.dataset.density = density;
}

export function setDensity(density: Density): void {
  localStorage.setItem(DENSITY_KEY, density);
  applyPreferences(density);
}

export function getAnnouncementNotifications(): boolean {
  if (typeof window === "undefined") return true;
  return localStorage.getItem(ANNOUNCEMENTS_KEY) !== "0";
}

export function getFollowUpNotifications(): boolean {
  if (typeof window === "undefined") return true;
  return localStorage.getItem(FOLLOW_UPS_KEY) !== "0";
}

export function setAnnouncementNotifications(value: boolean): void {
  localStorage.setItem(ANNOUNCEMENTS_KEY, value ? "1" : "0");
}

export function setFollowUpNotifications(value: boolean): void {
  localStorage.setItem(FOLLOW_UPS_KEY, value ? "1" : "0");
}
